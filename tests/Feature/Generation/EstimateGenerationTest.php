<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Cache::forget('pixelforge.pricing');
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/pricing' => Http::response(estimatePricingPayload()),
    ]);
});

it('estimates the documented maximum case', function () {
    $this->withoutVite()
        ->actingAs(User::factory()->create())
        ->get(route('generations.create', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => [
                'https://example.com/a.png',
                'https://example.com/b.png',
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Create')
            ->where('estimate.min', '$0,357781')
            ->where('estimate.max', '$0,467344'));
});

it('estimates the default form when the query omits size and quality', function () {
    $this->withoutVite()
        ->actingAs(User::factory()->create())
        ->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Create')
            ->where('estimate.min', '$0,058125')
            ->where('estimate.max', '$0,08625'));
});

it('reuses the cached pricing when the prompt changes', function () {
    $user = User::factory()->create();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.min', '$0,058125')
            ->where('estimate.max', '$0,08625'));

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.create', [
            'prompt' => str_repeat('a', 400),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.min', '$0,058656')
            ->where('estimate.max', '$0,086969'));

    Http::assertSentCount(1);
});

/**
 * @return array<string, mixed>
 */
function estimatePricingPayload(): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => [
                    '1024x1024' => 280,
                    '1536x1024' => 420,
                    '1024x1536' => 420,
                ],
                'medium' => [
                    '1024x1024' => 1100,
                    '1536x1024' => 1650,
                    '1024x1536' => 1650,
                ],
                'high' => [
                    '1024x1024' => 4300,
                    '1536x1024' => 6450,
                    '1024x1536' => 6450,
                ],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}
