<?php

namespace App\Support;

/** Pomocnik do rysowania gładkich linii wykresu w SVG (bez bibliotek JS). */
class Chart
{
    /** @param array<int, array{0: float, 1: float}> $points */
    public static function smoothPath(array $points): string
    {
        $n = count($points);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return sprintf('M%.1f %.1f', ...$points[0]);
        }
        $d = sprintf('M%.1f %.1f', ...$points[0]);
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $points[max(0, $i - 1)];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[min($n - 1, $i + 2)];
            $c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6];
            $c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6];
            $d .= sprintf(' C%.1f %.1f, %.1f %.1f, %.1f %.1f', $c1[0], $c1[1], $c2[0], $c2[1], $p2[0], $p2[1]);
        }

        return $d;
    }

    /** Ładna górna granica osi Y. */
    public static function niceMax(int $max): int
    {
        // Cztery równe podziałki z "ładnym" krokiem (1, 2, 3, 5, 10, 20…).
        $raw = max(1, $max) / 4;
        $mag = 10 ** floor(log10($raw));
        foreach ([1, 2, 3, 5, 10] as $m) {
            if ($m * $mag >= $raw) {
                return (int) ceil($m * $mag * 4);
            }
        }

        return (int) ceil(10 * $mag * 4);
    }
}
