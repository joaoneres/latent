<?php

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/pricing' => Http::response(creditPricingPayload()),
    ]);
});

it('shows the sum of two purchases on the form and the history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('credits.store'), ['amount_nano' => 1_000_000_000])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('credits.store'), ['amount_nano' => 5_000_000_000])
        ->assertRedirect();

    Http::assertNothingSent();

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(6_000_000_000);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('creditBalance', '$6,0000'));

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('creditBalance', '$6,0000'));
});

it('holds the estimated maximum when the balance covers it', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000);

    $this->actingAs($user)
        ->post(route('generations.store'), creditGenerationPayload())
        ->assertRedirect(route('generations.index'));

    $generation = Generation::query()->whereBelongsTo($user)->firstOrFail();
    $hold = CreditTransaction::query()->where('type', 'hold')->firstOrFail();

    expect($generation->estimated_charge_nano)->toBe(600_000_000)
        ->and($hold->generation_id)->toBe($generation->id)
        ->and($hold->amount_nano)->toBe(-600_000_000)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(400_000_000);
});

it('refuses a generation when the balance is below the maximum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('generations.create'))
        ->post(route('generations.store'), creditGenerationPayload())
        ->assertRedirect(route('generations.create'))
        ->assertSessionHasErrors([
            'balance' => 'Saldo insuficiente. Esta geração custa até $0,6000.',
        ]);

    expect(Generation::query()->count())->toBe(0)
        ->and(CreditTransaction::query()->count())->toBe(0);
});

it('returns the hold when the generation fails', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000);

    $this->actingAs($user)->post(route('generations.store'), creditGenerationPayload());

    $generation = Generation::query()->whereBelongsTo($user)->firstOrFail();
    app(ApplyGenerationState::class)->fail($generation, 'network_error', 'Pixelforge não respondeu.');

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000)
        ->and(CreditTransaction::query()->where('type', 'release')->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', 'charge')->count())->toBe(0);
});

it('charges the real amount once when the generation succeeds', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000);

    $this->actingAs($user)->post(route('generations.store'), creditGenerationPayload());

    $generation = Generation::query()->whereBelongsTo($user)->firstOrFail();
    $state = app(ApplyGenerationState::class);
    $state->fromProvider($generation, creditSucceededObject());

    $generation->refresh();

    expect($generation->charge_nano)->toBe(467_343_750)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 467_343_750)
        ->and(CreditTransaction::query()->where('type', 'hold')->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', 'release')->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', 'charge')->value('amount_nano'))->toBe(-467_343_750);

    $state->fromProvider($generation, creditSucceededObject());

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 467_343_750)
        ->and(CreditTransaction::query()->where('type', 'charge')->count())->toBe(1);
});

it('refuses a second generation that fits the purchase but not the balance after the hold', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000);

    $this->actingAs($user)
        ->post(route('generations.store'), creditGenerationPayload())
        ->assertRedirect(route('generations.index'));

    $this->actingAs($user)
        ->from(route('generations.create'))
        ->post(route('generations.store'), creditGenerationPayload())
        ->assertSessionHasErrors('balance');

    expect(Generation::query()->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', 'hold')->count())->toBe(1)
        ->and(Generation::query()->firstOrFail()->estimated_charge_nano)->toBe(600_000_000)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(400_000_000);
});

/**
 * @return array<string, string>
 */
function creditGenerationPayload(): array
{
    return [
        'prompt' => 'aaaa',
        'size' => '1024x1024',
        'quality' => 'high',
    ];
}

/**
 * @return array<string, mixed>
 */
function creditPricingPayload(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '0.00',
            'input_image_tokens' => '0.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '0.00',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0,
            'per_reference_image' => 0,
            'output' => [
                'low' => [
                    '1024x1024' => 0,
                    '1536x1024' => 0,
                    '1024x1536' => 0,
                ],
                'medium' => [
                    '1024x1024' => 0,
                    '1536x1024' => 0,
                    '1024x1536' => 0,
                ],
                'high' => [
                    '1024x1024' => 12000,
                    '1536x1024' => 6450,
                    '1024x1536' => 6450,
                ],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 0],
                'medium' => ['min' => 0, 'max' => 0],
                'high' => ['min' => 0, 'max' => 0],
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function creditSucceededObject(): array
{
    return [
        'id' => 'gen_done',
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'completed_at' => '2026-11-04T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ],
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    ];
}
