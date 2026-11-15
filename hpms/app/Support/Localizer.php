<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class Localizer
{
    private const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    public static function isBangla(): bool
    {
        return app()->getLocale() === 'bn';
    }

    /**
     * Convert an already formatted numeric string for display. Calculations
     * and database values always stay in Latin numerals.
     */
    public static function digits(string $value): string
    {
        if (! self::isBangla()) {
            return $value;
        }

        return str_replace(range(0, 9), self::BENGALI_DIGITS, $value);
    }

    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        return self::digits(number_format((float) ($value ?? 0), $decimals));
    }

    public static function money(int|float|string|null $value, int $decimals = 2): string
    {
        return '৳'.self::number($value, $decimals);
    }

    /**
     * Format a date through Carbon's locale data, then localize its numerals.
     */
    public static function date(CarbonInterface|string|null $date, string $format = 'd M Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);

        return self::digits($carbon->locale(app()->getLocale())->translatedFormat($format));
    }

    public static function dateTime(CarbonInterface|string|null $date, string $format = 'd M Y, h:i A'): string
    {
        return self::date($date, $format);
    }

    /**
     * Translate a system-provided label (a default category, form, unit, or
     * movement type) without ever changing the stored value.
     */
    public static function label(?string $value, string $group): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $key = 'defaults.'.$group.'.'.$value;

        return Lang::has($key) ? __($key) : Str::headline($value);
    }

    /**
     * Human-readable labels for persisted status values.
     */
    public static function status(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $key = 'defaults.statuses.'.$status;

        return Lang::has($key) ? __($key) : Str::headline($status);
    }
}
