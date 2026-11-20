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

    /**
     * @param  array{
     *     rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string},
     *     tokens: array{
     *         characters_per_text_token: int,
     *         text_token_variance: float|int|string,
     *         per_reference_image: int,
     *         output: array<string, array<string, int>>,
     *         reasoning: array<string, array{min: int, max: int}>
     *     }
     * }  $pricing
     * @return array{min: int, max: int}
     */
    public function estimateChargeNano(string $prompt, string $quality, string $size, int $referenceImageCount, array $pricing): array
    {
        $tokens = $pricing['tokens'];
        $base = BigDecimal::of(mb_strlen($prompt))
            ->dividedBy($tokens['characters_per_text_token'], 0, RoundingMode::Ceiling)
            ->toInt();
        $variance = BigDecimal::of((string) $tokens['text_token_variance']);
        $minText = BigDecimal::of($base)
            ->multipliedBy(BigDecimal::of(1)->minus($variance))
            ->toScale(0, RoundingMode::Floor)
            ->toInt();
        $maxText = BigDecimal::of($base)
            ->multipliedBy(BigDecimal::of(1)->plus($variance))
            ->toScale(0, RoundingMode::Ceiling)
            ->toInt();

        $imageTokens = $tokens['per_reference_image'] * $referenceImageCount;
        $outputTokens = $tokens['output'][$quality][$size];

        $min = $this->userChargeNano($this->providerCostNano([
            'input_text_tokens' => max(0, $minText),
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $tokens['reasoning'][$quality]['min'],
        ], $pricing['rates']));
        $max = $this->userChargeNano($this->providerCostNano([
            'input_text_tokens' => $maxText,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $tokens['reasoning'][$quality]['max'],
        ], $pricing['rates']));

        return ['min' => $min, 'max' => $max];
    }
}
