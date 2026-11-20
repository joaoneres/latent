<?php

namespace App\Services\Pixelforge;

use App\Clients\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Cache;

final class PricingCatalog
{
    public function __construct(private PixelforgeClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return Cache::remember('pixelforge.pricing', 300, fn (): array => $this->client->pricing());
    }
}
