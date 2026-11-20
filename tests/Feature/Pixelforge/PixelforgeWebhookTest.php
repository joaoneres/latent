<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'services.pixelforge.webhook_secret' => 'whsec_test',
    ]);
});

it('applies a succeeded generation and charges once', function () {
    $generation = makeWebhookGeneration();
    $generation->pixelforge_id = 'gen_done';
    $generation->save();

    postPixelforgeWebhook(succeededWebhookPayload('evt_succeeded', $generation))->assertOk();

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano)->toBe(467343750)
        ->and($generation->provider_cost_nano)->toBe(373875000)
        ->and(WebhookEvent::query()->count())->toBe(1)
        ->and(WebhookEvent::query()->firstOrFail()->processed_at)->not->toBeNull();
});

it('ignores a repeated event id without charging again', function () {
    $generation = makeWebhookGeneration();
    $generation->pixelforge_id = 'gen_done';
    $generation->save();

    $payload = succeededWebhookPayload('evt_succeeded', $generation);

    postPixelforgeWebhook($payload, headers: ['HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '1'])->assertOk();

    $charge = $generation->fresh()->charge_nano;

    postPixelforgeWebhook($payload, headers: ['HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '2'])->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and($generation->fresh()->charge_nano)->toBe($charge);
});

it('rejects an invalid signature without storing the event', function () {
    $generation = makeWebhookGeneration();

    postPixelforgeWebhook(
        succeededWebhookPayload('evt_bad', $generation),
        v1: str_repeat('ab', 32),
    )->assertStatus(400);

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Pending)
        ->and($generation->fresh()->charge_nano)->toBeNull();
});

it('rejects a timestamp older than five minutes', function () {
    $generation = makeWebhookGeneration();

    postPixelforgeWebhook(
        succeededWebhookPayload('evt_old', $generation),
        timestamp: time() - 301,
    )->assertStatus(400);

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and(Generation::query()->count())->toBe(1)
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Pending);
});

it('stores a ping without changing the generation', function () {
    $generation = makeWebhookGeneration();

    postPixelforgeWebhook([
        'id' => 'evt_ping',
        'object' => 'event',
        'type' => 'ping',
    ])->assertOk();

    $event = WebhookEvent::query()->firstOrFail();
    $generation->refresh();

    expect($event->type)->toBe('ping')
        ->and($event->processed_at)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->pixelforge_id)->toBeNull()
        ->and($generation->charge_nano)->toBeNull();
});

it('matches a generation by metadata when pixelforge id is still empty', function () {
    $generation = makeWebhookGeneration();

    $payload = succeededWebhookPayload('evt_early', $generation);
    $payload['data']['object']['id'] = 'gen_early';

    postPixelforgeWebhook($payload)->assertOk();

    expect($generation->fresh()->pixelforge_id)->toBe('gen_early');
});

it('keeps a succeeded generation when a later processing webhook arrives', function () {
    $generation = makeWebhookGeneration();
    $generation->pixelforge_id = 'gen_done';
    $generation->save();

    postPixelforgeWebhook(succeededWebhookPayload('evt_succeeded', $generation))->assertOk();

    $charge = $generation->fresh()->charge_nano;

    postPixelforgeWebhook([
        'id' => 'evt_processing',
        'object' => 'event',
        'type' => 'generation.processing',
        'data' => [
            'object' => [
                'id' => 'gen_done',
                'object' => 'generation',
                'status' => 'processing',
                'metadata' => [
                    'generation_id' => (string) $generation->id,
                ],
            ],
        ],
    ])->assertOk();

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano)->toBe($charge);
});

it('accepts a signed webhook without a csrf token', function () {
    postPixelforgeWebhook([
        'id' => 'evt_ping_csrf',
        'object' => 'event',
        'type' => 'ping',
    ])->assertOk();
});

/**
 * @param  array<string, string>  $headers
 */
function postPixelforgeWebhook(array $payload, ?int $timestamp = null, ?string $v1 = null, array $headers = []): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp ??= time();
    $secret = config('services.pixelforge.webhook_secret');
    $signature = $v1 ?? hash_hmac('sha256', $timestamp.'.'.$body, is_string($secret) ? $secret : '');

    return test()->call(
        'POST',
        '/webhooks/pixelforge',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ...$headers,
        ],
        $body,
    );
}

function makeWebhookGeneration(): Generation
{
    return User::factory()->create()->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1536x1024',
        'quality' => 'high',
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 1,
    ]);
}

/**
 * @return array<string, mixed>
 */
function succeededWebhookPayload(string $eventId, Generation $generation): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => 'generation.succeeded',
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => [
            'object' => [
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
                'metadata' => [
                    'generation_id' => (string) $generation->id,
                ],
            ],
        ],
    ];
}
