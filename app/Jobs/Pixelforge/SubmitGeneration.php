<?php

namespace App\Jobs\Pixelforge;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Enums\PixelforgeErrorCode;
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

    private Generation $generation;

    private Response $response;

    private ApplyGenerationState $state;

    public function __construct(public int $generationId) {}

    public function handle(PixelforgeClient $client, ApplyGenerationState $state): void
    {
        $this->state = $state;
        $this->load();
        $this->post($client);
    }

    private function load(): void
    {
        $generation = Generation::query()->findOrFail($this->generationId);
        $generation->submit_attempts++;
        $generation->save();
        $this->generation = $generation;
    }

    private function post(PixelforgeClient $client): void
    {
        try {
            $this->response = $client->createGeneration($this->generation->idempotency_key, $this->body());
        } catch (ClientException) {
            $this->failNetwork();

            return;
        }

        $this->applyResponse();
    }

    /**
     * @return array{prompt: string, size: string, quality: string, reference_images: mixed, metadata: array{generation_id: string}}
     */
    private function body(): array
    {
        return [
            'prompt' => $this->generation->prompt,
            'size' => $this->generation->size,
            'quality' => $this->generation->quality,
            'reference_images' => $this->generation->reference_images ?? [],
            'metadata' => [
                'generation_id' => (string) $this->generation->id,
            ],
        ];
    }

    private function applyResponse(): void
    {
        if ($this->response->status() === 202) {
            $this->accept();

            return;
        }

        if ($this->response->status() === 429 && $this->errorCode() === PixelforgeErrorCode::DailyLimitExceeded) {
            $this->failDaily();

            return;
        }

        if ($this->shouldRetry()) {
            $this->retryOrFail();

            return;
        }

        if (($this->response->status() === 400 && $this->errorCode() === PixelforgeErrorCode::ContentPolicyViolation) || $this->response->status() === 422) {
            $this->failWithApiMessage();

            return;
        }

        if ($this->response->status() === 401) {
            $this->failAuthentication();

            return;
        }

        if ($this->response->status() === 409) {
            $this->failWithApiMessage();
        }
    }

    private function accept(): void
    {
        $object = $this->response->json();

        if (is_array($object)) {
            $this->state->fromProvider($this->generation, $object);
        }
    }

    private function failDaily(): void
    {
        $this->state->fail($this->generation, PixelforgeErrorCode::DailyLimitExceeded->value, 'Daily limit reached.');
    }

    private function shouldRetry(): bool
    {
        return $this->errorCode()?->retriesOn($this->response->status()) ?? false;
    }

    private function retryOrFail(): void
    {
        if ($this->attempts() >= 5) {
            $this->state->fail($this->generation, $this->rawErrorCode(), $this->errorMessage());

            return;
        }

        $this->release(max((int) $this->response->header('Retry-After'), 5 * $this->attempts()));
    }

    private function failNetwork(): void
    {
        if ($this->attempts() >= 5) {
            $this->state->fail($this->generation, PixelforgeErrorCode::NetworkError->value, 'Pixelforge did not respond.');

            return;
        }

        $this->release(5 * $this->attempts());
    }

    private function failAuthentication(): void
    {
        Log::error('Pixelforge authentication failed.', [
            'generation_id' => $this->generation->id,
        ]);
        $code = $this->rawErrorCode();
        $message = $this->errorMessage();
        $this->state->fail(
            $this->generation,
            $code !== '' ? $code : PixelforgeErrorCode::InvalidApiKey->value,
            $message !== '' ? $message : 'Pixelforge authentication failed.',
        );
    }

    private function failWithApiMessage(): void
    {
        $this->state->fail($this->generation, $this->rawErrorCode(), $this->errorMessage());
    }

    private function errorCode(): ?PixelforgeErrorCode
    {
        return PixelforgeErrorCode::tryFrom($this->rawErrorCode());
    }

    private function rawErrorCode(): string
    {
        $error = $this->response->json('error');

        return is_array($error) && isset($error['code']) ? (string) $error['code'] : '';
    }

    private function errorMessage(): string
    {
        $error = $this->response->json('error');

        return is_array($error) && isset($error['message']) ? (string) $error['message'] : '';
    }
}
