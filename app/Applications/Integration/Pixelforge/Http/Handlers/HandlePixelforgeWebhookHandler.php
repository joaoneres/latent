<?php

namespace App\Applications\Integration\Pixelforge\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Enums\PixelforgeEventType;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Http\Request;

class HandlePixelforgeWebhookHandler implements HandlesHttpRequest
{
    private string $rawBody = '';

    /** @var array<string, mixed> */
    private array $payload = [];

    private string $eventId = '';

    private string $type = '';

    public function __construct(
        private Request $request,
        private ApplyGenerationState $state,
    ) {}

    public function handle(): void
    {
        $this->assertSignature($this->request);
        $this->readPayload();

        if (! $this->storeEvent()) {
            return;
        }

        $this->applyEvent();
    }

    private function assertSignature(Request $request): void
    {
        $this->rawBody = $request->getContent();
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
        $expected = hash_hmac('sha256', "{$timestamp}.{$this->rawBody}", is_string($secret) ? $secret : '');

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

    private function readPayload(): void
    {
        $payload = json_decode($this->rawBody, true);

        if (! is_array($payload) || ! isset($payload['id'], $payload['type']) || ! is_string($payload['id']) || ! is_string($payload['type'])) {
            abort(400);
        }

        $this->payload = $payload;
        $this->eventId = $payload['id'];
        $this->type = $payload['type'];
    }

    private function storeEvent(): bool
    {
        $inserted = WebhookEvent::query()->insertOrIgnore([
            'event_id' => $this->eventId,
            'type' => $this->type,
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted !== 0;
    }

    private function applyEvent(): void
    {
        $type = PixelforgeEventType::tryFrom($this->type);

        if ($type === PixelforgeEventType::Ping) {
            $this->markProcessed();

            return;
        }

        if ($type === null || ! $type->appliesToGeneration()) {
            return;
        }

        $this->applyGeneration();
    }

    private function applyGeneration(): void
    {
        $data = $this->payload['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        if (! is_array($object)) {
            $this->dropEvent();
            abort(500);
        }

        $generation = $this->findGeneration($object);

        if (! $generation instanceof Generation) {
            $this->dropEvent();
            abort(500);
        }

        $this->state->fromProvider($generation, $this->providerObject($object));
        $this->markProcessed();
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

    private function markProcessed(): void
    {
        $event = WebhookEvent::query()->where('event_id', $this->eventId)->first();

        if (! $event instanceof WebhookEvent) {
            return;
        }

        $event->processed_at = now();
        $event->save();
    }

    private function dropEvent(): void
    {
        WebhookEvent::query()->where('event_id', $this->eventId)->delete();
    }
}
