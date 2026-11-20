<?php

namespace App\Clients\Pixelforge;

use App\Exceptions\ClientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    /**
     * @param  array<string, mixed>  $options
     *
     * @throws ClientException
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        try {
            $response = $this->request()->send($method, $url, $options);
        } catch (ConnectionException $exception) {
            throw new ClientException($this->redact($exception->getMessage()), $exception);
        }

        if (! $response->successful()) {
            $body = $this->redact($response->body());

            throw new ClientException(
                "HTTP request returned status code {$response->status()}:\n{$body}",
                $response->toException() ?? new RequestException($response),
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClientException
     */
    public function pricing(): array
    {
        $payload = $this->send('GET', 'pricing')->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws ClientException
     */
    public function createGeneration(string $idempotencyKey, array $body): Response
    {
        if (array_key_exists('reference_images', $body) && ($body['reference_images'] === [] || $body['reference_images'] === null)) {
            unset($body['reference_images']);
        }

        try {
            return $this->request()
                ->asJson()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('generations', $body);
        } catch (ConnectionException $exception) {
            throw new ClientException($this->redact($exception->getMessage()), $exception);
        }
    }

    private function request(): PendingRequest
    {
        $pixelforge = config('services.pixelforge');
        $pixelforge = is_array($pixelforge) ? $pixelforge : [];

        $baseUrl = $pixelforge['base_url'] ?? '';
        $token = $pixelforge['api_key'] ?? '';

        return Http::withToken(is_string($token) ? $token : '')
            ->baseUrl(is_string($baseUrl) ? $baseUrl : '')
            ->acceptJson()
            ->timeout(15);
    }

    private function redact(string $value): string
    {
        $pixelforge = config('services.pixelforge');
        $token = is_array($pixelforge) ? ($pixelforge['api_key'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            return $value;
        }

        return str_replace($token, '[redacted]', $value);
    }
}
