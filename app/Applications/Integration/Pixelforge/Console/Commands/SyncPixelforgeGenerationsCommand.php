<?php

namespace App\Applications\Integration\Pixelforge\Console\Commands;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Enums\GenerationStatus;
use App\Enums\PixelforgeErrorCode;
use App\Exceptions\ClientException;
use App\Models\Generation;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SyncPixelforgeGenerationsCommand extends Command
{
    protected $signature = 'pixelforge:sync';

    private PixelforgeClient $client;

    private ApplyGenerationState $state;

    public function handle(PixelforgeClient $client, ApplyGenerationState $state): int
    {
        $this->client = $client;
        $this->state = $state;
        $this->sync();

        return self::SUCCESS;
    }

    private function sync(): void
    {
        foreach ($this->pending() as $generation) {
            if (! $this->fetch($generation)) {
                return;
            }
        }
    }

    /**
     * @return Collection<int, Generation>
     */
    private function pending(): Collection
    {
        return Generation::query()
            ->whereNotNull('pixelforge_id')
            ->whereNotIn('status', [
                GenerationStatus::Succeeded->value,
                GenerationStatus::Failed->value,
            ])
            ->where('submitted_at', '<', now()->subMinutes(2))
            ->orderBy('id')
            ->get();
    }

    private function fetch(Generation $generation): bool
    {
        $pixelforgeId = $generation->pixelforge_id;

        if (! is_string($pixelforgeId) || $pixelforgeId === '') {
            return true;
        }

        try {
            $response = $this->client->generation($pixelforgeId);
        } catch (ClientException) {
            return true;
        }

        return $this->applyResponse($generation, $response);
    }

    private function applyResponse(Generation $generation, Response $response): bool
    {
        if ($response->status() === 200) {
            $this->accept($generation, $response);

            return true;
        }

        if ($response->status() === 404) {
            $this->failMissing($generation, $response);

            return true;
        }

        if ($response->status() === 429 || $response->status() === 503) {
            return $this->stopForLimit();
        }

        if ($response->status() === 401) {
            return $this->stopForAuthentication($generation);
        }

        return true;
    }

    private function accept(Generation $generation, Response $response): void
    {
        $object = $response->json();

        if (is_array($object)) {
            $this->state->fromProvider($generation, $this->providerObject($object));
        }
    }

    private function failMissing(Generation $generation, Response $response): void
    {
        $this->state->fail($generation, PixelforgeErrorCode::NotFound->value, $this->notFoundMessage($response));
    }

    private function stopForLimit(): bool
    {
        return false;
    }

    private function stopForAuthentication(Generation $generation): bool
    {
        Log::error('Pixelforge authentication failed.', [
            'generation_id' => $generation->id,
        ]);

        return false;
    }

    private function notFoundMessage(Response $response): string
    {
        $error = $response->json('error');
        $message = is_array($error) ? ($error['message'] ?? null) : null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'Generation not found.';
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
}
