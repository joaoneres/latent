<?php

use App\Services\Pixelforge\PricingCalculator;

it('calculates the documented maximum provider cost', function () {
    $cost = (new PricingCalculator)->providerCostNano(
        [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ],
        [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    );

    expect($cost)->toBe(373875000);
});

it('calculates the documented usage provider cost', function () {
    $cost = (new PricingCalculator)->providerCostNano(
        [
            'input_text_tokens' => 41,
            'input_image_tokens' => 0,
            'output_tokens' => 3300,
            'reasoning_tokens' => 2950,
        ],
        [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    );

    expect($cost)->toBe(169080000);
});

it('rounds a one nano provider cost up to two', function () {
    expect((new PricingCalculator)->userChargeNano(1))->toBe(2);
});

it('estimates the documented maximum generation as a marked-up range', function () {
    $estimate = (new PricingCalculator)->estimateChargeNano(
        str_repeat('a', 400),
        'high',
        '1536x1024',
        2,
        pricing(),
    );

    expect($estimate)->toBe([
        'min' => 357781250,
        'max' => 467343750,
    ]);
});

it('counts keyword characters in the low-quality estimate', function () {
    $estimate = (new PricingCalculator)->estimateChargeNano(
        '[fail]',
        'low',
        '1024x1024',
        0,
        pricing(),
    );

    expect($estimate)->toBe([
        'min' => 14006250,
        'max' => 21831250,
    ]);
});

/**
 * @return array{
 *     rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string},
 *     tokens: array{
 *         characters_per_text_token: int,
 *         text_token_variance: float,
 *         per_reference_image: int,
 *         output: array<string, array<string, int>>,
 *         reasoning: array<string, array{min: int, max: int}>
 *     }
 * }
 */
function pricing(): array
{
    return [
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
                ],
                'high' => [
                    '1536x1024' => 6450,
                ],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}
