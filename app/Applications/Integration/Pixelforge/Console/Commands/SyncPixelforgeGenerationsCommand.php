<?php

namespace App\Applications\Integration\Pixelforge\Console\Commands;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Enums\GenerationStatus;
use App\Exceptions\ClientException;
use App\Models\Generation;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class SyncPixelforgeGenerationsCommand extends Command
{
    protected $signature = 'pixelforge:sync';

    public function handle(PixelforgeClient $client, ApplyGenerationState $state): int
    {
        $generations = Generation::query()
            ->whereNotNull('pixelforge_id')
            ->whereNotIn('status', [
                GenerationStatus::Succeeded->value,
                GenerationStatus::Failed->value,
            ])
            ->where('submitted_at', '<', now()->subMinutes(2))
            ->orderBy('id')
            ->get();

        foreach ($generations as $generation) {
            $pixelforgeId = $generation->pixelforge_id;

            if (! is_string($pixelforgeId) || $pixelforgeId === '') {
                continue;
            }

            try {
                $response = $client->generation($pixelforgeId);
            } catch (ClientException) {
                continue;
            }

            if ($response->status() === 200) {
                $object = $response->json();

                if (is_array($object)) {
                    $state->fromProvider($generation, $this->providerObject($object));
                }

                continue;
            }

            if ($response->status() === 404) {
                $state->fail($generation, 'not_found', $this->notFoundMessage($response));

                continue;
            }

            if ($response->status() === 429 || $response->status() === 503) {
                break;
            }

            if ($response->status() === 401) {
                Log::error('Pixelforge authentication failed.', [
                    'generation_id' => $generation->id,
                ]);

                break;
            }
        }

        return self::SUCCESS;
    }

    private function notFoundMessage(Response $response): string
    {
        $error = $response->json('error');
        $message = is_array($error) ? ($error['message'] ?? null) : null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'geração não encontrada';
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
