<?php

use App\Clients\Pixelforge\PixelforgeClient;
use App\Enums\GenerationStatus;
use App\Jobs\Pixelforge\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReleaseRecordingSubmitGeneration extends SubmitGeneration
{
    public ?int $releasedFor = null;

    public int $attempt = 1;

    public function attempts()
    {
        return $this->attempt;
    }

    public function release($delay = 0)
    {
        $this->releasedFor = is_numeric($delay) ? (int) $delay : 0;
    }
}

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Http::preventStrayRequests();
});

it('applies a queued generation from a 202 response', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_accepted',
            'status' => 'queued',
            'rates' => providerRates(),
        ], 202),
    ]);

    $generation = makeGeneration();
    $job = recordingJob($generation);
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_accepted')
        ->and($generation->rates)->toBe(providerRates())
        ->and($generation->submitted_at)->not->toBeNull()
        ->and($generation->charge_nano)->toBeNull()
        ->and($job->releasedFor)->toBeNull();
});

it('fails the daily limit without releasing the job', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'daily_limit_exceeded',
                'message' => 'The daily quota is used up.',
            ],
        ], 429),
    ]);

    $generation = makeGeneration();
    $job = recordingJob($generation);
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('daily_limit_exceeded')
        ->and($generation->error_message)->toBe('Daily limit reached.')
        ->and($generation->charge_nano)->toBe(0)
        ->and($generation->provider_cost_nano)->toBe(0)
        ->and($job->releasedFor)->toBeNull();
});

it('fails a content policy violation without charging', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'content_policy_violation',
                'message' => 'The prompt was rejected.',
            ],
        ], 400),
    ]);

    $generation = makeGeneration();
    recordingJob($generation)->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and($generation->error_message)->toBe('The prompt was rejected.')
        ->and($generation->charge_nano)->toBe(0)
        ->and($generation->provider_cost_nano)->toBe(0);
});

it('fails a validation error with the api message', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The selected size is invalid.',
            ],
        ], 422),
    ]);

    $generation = makeGeneration();
    recordingJob($generation)->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('validation_failed')
        ->and($generation->error_message)->toBe('The selected size is invalid.')
        ->and($generation->charge_nano)->toBe(0);
});

it('logs an authentication failure without the api key', function () {
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'invalid_api_key',
                'message' => 'The API key is invalid.',
            ],
        ], 401),
    ]);

    $generation = makeGeneration();
    recordingJob($generation)->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('invalid_api_key')
        ->and($records)->not->toBeEmpty();

    $encoded = json_encode($records);

    expect(is_string($encoded) ? $encoded : '')->not->toContain('pf_test_key');
});

it('releases a rate limit using the larger of retry after and backoff', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Slow down.',
            ],
        ], 429, ['Retry-After' => '2']),
    ]);

    $generation = makeGeneration();
    $job = recordingJob($generation);
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($job->releasedFor)->toBe(5)
        ->and($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->idempotency_key)->not->toBe('');
});

it('releases an overload using retry after when it is longer', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'overloaded',
                'message' => 'Busy.',
            ],
        ], 503, ['Retry-After' => '30']),
    ]);

    $generation = makeGeneration();
    $job = recordingJob($generation);
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    expect($job->releasedFor)->toBe(30)
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Pending);
});

it('retries a network failure with the same idempotency key', function () {
    $generation = makeGeneration();
    $key = $generation->idempotency_key;
    $seen = [];

    Http::fake(function (Request $request) use (&$seen) {
        $header = $request->header('Idempotency-Key');
        $seen[] = is_array($header) ? ($header[0] ?? null) : $header;

        throw new ConnectionException('timed out talking to pf_test_key');
    });

    $job = recordingJob($generation);
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($seen)->toBe([$key, $key])
        ->and($generation->idempotency_key)->toBe($key)
        ->and($generation->status)->toBe(GenerationStatus::Pending)
        ->and($job->releasedFor)->toBe(5);
});

it('fails the fifth rate limit attempt', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'code' => 'concurrency_limit_exceeded',
                'message' => 'Too many generations are in progress.',
            ],
        ], 429, ['Retry-After' => '5']),
    ]);

    $generation = makeGeneration();
    $job = recordingJob($generation);
    $job->attempt = 5;
    $job->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    $generation->refresh();

    expect($job->releasedFor)->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('concurrency_limit_exceeded')
        ->and($generation->error_message)->toBe('Too many generations are in progress.');
});

it('omits empty reference images and sends the public id', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_empty',
            'status' => 'queued',
            'rates' => providerRates(),
        ], 202),
    ]);

    $generation = makeGeneration();
    recordingJob($generation)->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    Http::assertSent(function (Request $request) use ($generation) {
        $data = $request->data();

        return $request->url() === 'https://pixelforge.test/v1/generations'
            && ! array_key_exists('reference_images', $data)
            && ($data['metadata']['generation_id'] ?? null) === $generation->public_id;
    });
});

it('sends reference images when the generation has them', function () {
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_refs',
            'status' => 'queued',
            'rates' => providerRates(),
        ], 202),
    ]);

    $generation = makeGeneration(['reference_images' => ['https://example.com/style.png']]);
    recordingJob($generation)->handle(app(PixelforgeClient::class), app(ApplyGenerationState::class));

    Http::assertSent(function (Request $request) use ($generation) {
        $data = $request->data();

        return ($data['reference_images'] ?? null) === ['https://example.com/style.png']
            && ($data['metadata']['generation_id'] ?? null) === $generation->public_id;
    });
});

function makeGeneration(array $overrides = []): Generation
{
    $user = User::factory()->create();

    return $user->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => [],
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 1,
        ...$overrides,
    ]);
}

function recordingJob(Generation $generation): ReleaseRecordingSubmitGeneration
{
    return new ReleaseRecordingSubmitGeneration($generation->id);
}

/**
 * @return array<string, string>
 */
function providerRates(): array
{
    return [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];
}
