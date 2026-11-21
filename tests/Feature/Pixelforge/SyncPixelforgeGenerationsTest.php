<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Http::preventStrayRequests();
});

it('marks an old queued generation succeeded and does not fetch it again', function () {
    $generation = makeSyncGeneration([
        'pixelforge_id' => 'gen_done',
        'submitted_at' => now()->subMinutes(3),
    ]);

    Http::fake([
        'https://pixelforge.test/v1/generations/gen_done' => Http::response(syncSucceededObject(), 200),
    ]);

    $this->artisan('pixelforge:sync')->assertSuccessful();

    $generation->refresh();
    $charge = $generation->charge_nano;
    $expiresAt = $generation->image_url_expires_at;

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($charge)->toBe(467343750)
        ->and($expiresAt?->equalTo(Carbon::parse('2026-11-05T14:03:29Z')))->toBeTrue();

    $this->artisan('pixelforge:sync')->assertSuccessful();

    $generation->refresh();

    expect($generation->charge_nano)->toBe($charge)
        ->and($generation->image_url_expires_at?->equalTo($expiresAt))->toBeTrue();

    Http::assertSentCount(1);
});

it('does not request a queued generation without a pixelforge id', function () {
    makeSyncGeneration([
        'submitted_at' => now()->subMinutes(3),
    ]);

    Http::fake();

    $this->artisan('pixelforge:sync')->assertSuccessful();

    Http::assertNothingSent();
});

it('does not request a generation submitted just now', function () {
    makeSyncGeneration([
        'pixelforge_id' => 'gen_fresh',
        'submitted_at' => now(),
    ]);

    Http::fake();

    $this->artisan('pixelforge:sync')->assertSuccessful();

    Http::assertNothingSent();
});

it('fails a generation the provider no longer has', function () {
    $generation = makeSyncGeneration([
        'pixelforge_id' => 'gen_missing',
        'submitted_at' => now()->subMinutes(3),
    ]);

    Http::fake([
        'https://pixelforge.test/v1/generations/gen_missing' => Http::response([
            'error' => [
                'code' => 'not_found',
                'message' => 'No such generation.',
            ],
        ], 404),
    ]);

    $this->artisan('pixelforge:sync')->assertSuccessful();

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->provider_cost_nano)->toBe(0)
        ->and($generation->charge_nano)->toBe(0)
        ->and($generation->error_code)->toBe('not_found')
        ->and($generation->error_message)->toBe('No such generation.');
});

it('leaves the batch open when the provider rejects the api key', function () {
    $generation = makeSyncGeneration([
        'pixelforge_id' => 'gen_denied',
        'submitted_at' => now()->subMinutes(3),
    ]);

    Http::fake([
        'https://pixelforge.test/v1/generations/gen_denied' => Http::response([
            'error' => [
                'code' => 'invalid_api_key',
                'message' => 'The API key is invalid.',
            ],
        ], 401),
    ]);

    $this->artisan('pixelforge:sync')->assertSuccessful();

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->charge_nano)->toBeNull();
});

it('stops the batch when the provider asks the sync to wait', function () {
    $limited = makeSyncGeneration([
        'pixelforge_id' => 'gen_limited',
        'submitted_at' => now()->subMinutes(3),
    ]);
    $later = makeSyncGeneration([
        'pixelforge_id' => 'gen_later',
        'submitted_at' => now()->subMinutes(3),
    ]);

    Http::fake([
        'https://pixelforge.test/v1/generations/gen_limited' => Http::response([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Slow down.',
            ],
        ], 429),
        'https://pixelforge.test/v1/generations/gen_later' => Http::response(syncSucceededObject(), 200),
    ]);

    $this->artisan('pixelforge:sync')->assertSuccessful();

    $limited->refresh();
    $later->refresh();

    expect($limited->status)->toBe(GenerationStatus::Queued)
        ->and($limited->charge_nano)->toBeNull()
        ->and($later->status)->toBe(GenerationStatus::Queued)
        ->and($later->charge_nano)->toBeNull();

    Http::assertSentCount(1);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeSyncGeneration(array $attributes = []): Generation
{
    $generation = User::factory()->create()->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1536x1024',
        'quality' => 'high',
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Queued,
        'estimated_charge_nano' => 1,
    ]);

    foreach ($attributes as $key => $value) {
        $generation->setAttribute($key, $value);
    }

    $generation->save();

    return $generation->fresh() ?? $generation;
}

/**
 * @return array<string, mixed>
 */
function syncSucceededObject(): array
{
    return [
        'id' => 'gen_done',
        'object' => 'generation',
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'completed_at' => '2026-11-04T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
            'total_tokens' => 16095,
        ],
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    ];
}
