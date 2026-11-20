<?php

namespace App\Services\Pixelforge;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class PricingCalculator
{
    public function nanoPerToken(string $rate): int
    {
        return BigDecimal::of($rate)->multipliedBy(1000)->toInt();
    }

    /**
     * @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    public function providerCostNano(array $usage, array $rates): int
    {
        $cost = 0;

        foreach ([
            'input_text_tokens',
            'input_image_tokens',
            'output_tokens',
            'reasoning_tokens',
        ] as $type) {
            $cost += $usage[$type] * $this->nanoPerToken($rates[$type]);
        }

        return $cost;
    }

    public function userChargeNano(int $costNano): int
    {
        return BigDecimal::of($costNano)
            ->multipliedBy(5)
            ->dividedBy(4, 0, RoundingMode::Ceiling)
            ->toInt();
    }
}
