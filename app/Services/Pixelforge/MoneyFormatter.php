<?php

namespace App\Services\Pixelforge;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class MoneyFormatter
{
    public function format(int $nano): string
    {
        $amount = BigDecimal::of($nano)->dividedBy('1000000000', 6, RoundingMode::HalfUp);
        [$whole, $fraction] = explode('.', $amount->toString(), 2);
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) < 4) {
            $fraction = str_pad($fraction, 4, '0');
        }

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

        return '$'.(is_string($grouped) ? $grouped : $whole).','.$fraction;
    }
}
