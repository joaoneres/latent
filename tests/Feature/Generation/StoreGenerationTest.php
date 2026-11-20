<?php

use App\Jobs\Pixelforge\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('stores a pending generation and dispatches submission', function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/pricing' => Http::response(pricingPayload()),
    ]);

    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->generations()->create([
        'prompt' => 'someone else',
        'size' => '1024x1024',
        'quality' => 'low',
        'idempotency_key' => (string) Str::uuid(),
        'status' => 'pending',
        'estimated_charge_nano' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('generations.store'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => [
                'https://example.com/a.png',
                'https://example.com/b.png',
            ],
        ])
        ->assertRedirect(route('generations.index'));

    $generation = Generation::query()->whereBelongsTo($user)->firstOrFail();

    expect($generation->status->value)->toBe('pending')
        ->and(Str::isUuid($generation->idempotency_key))->toBeTrue()
        ->and($generation->estimated_charge_nano)->toBe(467343750)
        ->and($generation->rates)->toBeNull();

    Queue::assertPushed(SubmitGeneration::class, function (SubmitGeneration $job) use ($generation) {
        return $job->generationId === $generation->id;
    });

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Index')
            ->has('generations', 1)
            ->where('generations.0.id', $generation->id)
            ->where('generations.0.status', 'pending'));
});

/**
 * @return array<string, mixed>
 */
function pricingPayload(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'high' => [
                    '1536x1024' => 6450,
                ],
            ],
            'reasoning' => [
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}
