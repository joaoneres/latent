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
