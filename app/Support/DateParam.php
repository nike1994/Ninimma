<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Data z adresu (?month=2026-11, ?week=…): błędna wartość daje bieżącą datę zamiast błędu 500. */
class DateParam
{
    public static function from(Request $request, string $key): CarbonImmutable
    {
        $value = $request->query($key);
        if (! is_string($value) || $value === '') {
            return CarbonImmutable::now();
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
