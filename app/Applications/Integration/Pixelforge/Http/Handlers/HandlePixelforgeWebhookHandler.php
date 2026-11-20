<?php

namespace App\Applications\Integration\Pixelforge\Http\Handlers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Http\Request;

class HandlePixelforgeWebhookHandler
{
    public function __construct(private ApplyGenerationState $state) {}

    public function handle(Request $request): void
    {
        $rawBody = $request->getContent();
        $this->assertSignature($request, $rawBody);

        $payload = json_decode($rawBody, true);

        if (! is_array($payload) || ! isset($payload['id'], $payload['type']) || ! is_string($payload['id']) || ! is_string($payload['type'])) {
            abort(400);
        }

        $eventId = $payload['id'];
        $type = $payload['type'];

        $inserted = WebhookEvent::query()->insertOrIgnore([
            'event_id' => $eventId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return;
        }

        if ($type === 'ping') {
            $this->markProcessed($eventId);

            return;
        }

        if (! in_array($type, ['generation.processing', 'generation.succeeded', 'generation.failed'], true)) {
            return;
        }

        $data = $payload['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        if (! is_array($object)) {
            WebhookEvent::query()->where('event_id', $eventId)->delete();
            abort(500);
        }

        $generation = $this->findGeneration($object);

        if (! $generation instanceof Generation) {
            WebhookEvent::query()->where('event_id', $eventId)->delete();
            abort(500);
        }

        $this->state->fromProvider($generation, $this->providerObject($object));
        $this->markProcessed($eventId);
    }

    private function assertSignature(Request $request, string $rawBody): void
    {
        $header = $request->header('Pixelforge-Signature', '');

        if ($header === '') {
            abort(400);
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $provided = $parts['v1'] ?? '';
        $secret = config('services.pixelforge.webhook_secret');
        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", is_string($secret) ? $secret : '');

        if (strlen($provided) !== strlen($expected)) {
            abort(400);
        }

        if (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            abort(400);
        }

        if (! hash_equals($expected, $provided)) {
            abort(400);
        }
    }

    /**
     * @param  array<mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $pixelforgeId = isset($object['id']) && is_scalar($object['id']) ? (string) $object['id'] : '';

        if ($pixelforgeId !== '') {
            $generation = Generation::query()->where('pixelforge_id', $pixelforgeId)->first();

            if ($generation instanceof Generation) {
                return $generation;
            }
        }

        $metadata = $object['metadata'] ?? null;
        $localId = is_array($metadata) ? ($metadata['generation_id'] ?? null) : null;

        if (! is_string($localId) && ! is_int($localId)) {
            return null;
        }

        if ($localId === '') {
            return null;
        }

        $generation = Generation::query()->find($localId);

        return $generation instanceof Generation ? $generation : null;
    }

    /**
     * @param  array<mixed>  $object
     * @return array<string, mixed>
     */
    private function providerObject(array $object): array
    {
        $normalized = [];

        foreach ($object as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function markProcessed(string $eventId): void
    {
        $event = WebhookEvent::query()->where('event_id', $eventId)->first();

        if (! $event instanceof WebhookEvent) {
            return;
        }

        $event->processed_at = now();
        $event->save();
    }
}
