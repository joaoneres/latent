<?php

namespace App\Jobs\Pixelforge;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Exceptions\ClientException;
use App\Models\Generation;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(public int $generationId) {}

    public function handle(PixelforgeClient $client, ApplyGenerationState $state): void
    {
        $generation = Generation::query()->findOrFail($this->generationId);
        $generation->submit_attempts++;
        $generation->save();

        $body = [
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => $generation->reference_images ?? [],
            'metadata' => [
                'generation_id' => (string) $generation->id,
            ],
        ];

        try {
            $response = $client->createGeneration($generation->idempotency_key, $body);
        } catch (ClientException) {
            if ($this->attempts() >= 5) {
                $state->fail($generation, 'network_error', 'Pixelforge não respondeu.');

                return;
            }

            $this->release(5 * $this->attempts());

            return;
        }

        if ($response->status() === 202) {
            $object = $response->json();

            if (is_array($object)) {
                $state->fromProvider($generation, $object);
            }

            return;
        }

        $code = $this->errorCode($response);
        $message = $this->errorMessage($response);

        if ($response->status() === 429 && $code === 'daily_limit_exceeded') {
            $state->fail($generation, 'daily_limit_exceeded', 'limite diário atingido');

            return;
        }

        if (
            ($response->status() === 429 && in_array($code, ['rate_limit_exceeded', 'concurrency_limit_exceeded'], true))
            || ($response->status() === 503 && $code === 'overloaded')
        ) {
            if ($this->attempts() >= 5) {
                $state->fail($generation, $code, $message);

                return;
            }

            $this->release(max((int) $response->header('Retry-After'), 5 * $this->attempts()));

            return;
        }

        if (($response->status() === 400 && $code === 'content_policy_violation') || $response->status() === 422) {
            $state->fail($generation, $code, $message);

            return;
        }

        if ($response->status() === 401) {
            Log::error('Pixelforge authentication failed.', [
                'generation_id' => $generation->id,
            ]);
            $state->fail(
                $generation,
                $code !== '' ? $code : 'invalid_api_key',
                $message !== '' ? $message : 'Pixelforge authentication failed.',
            );

            return;
        }

        if ($response->status() === 409) {
            $state->fail($generation, $code, $message);
        }
    }

    private function errorCode(Response $response): string
    {
        $error = $response->json('error');

        return is_array($error) && isset($error['code']) ? (string) $error['code'] : '';
    }

    private function errorMessage(Response $response): string
    {
        $error = $response->json('error');

        return is_array($error) && isset($error['message']) ? (string) $error['message'] : '';
    }
}
