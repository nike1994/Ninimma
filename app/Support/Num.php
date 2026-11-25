<?php

namespace App\Support;

class Num
{
    /** 286500 → "286,5 tys.", 3469 → "3 469" */
    public static function short(int|float $n): string
    {
        if ($n >= 1_000_000) {
            return str_replace('.', ',', (string) round($n / 1_000_000, 1)).' '.__('social.million');
        }
        if ($n >= 10_000) {
            return str_replace('.', ',', (string) round($n / 1000, 1)).' '.__('social.thousand');
        }

        return number_format($n, 0, ',', ' ');
    }
}
