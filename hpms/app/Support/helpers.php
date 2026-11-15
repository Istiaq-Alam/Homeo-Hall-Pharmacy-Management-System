<?php

use App\Support\Localizer;
use Carbon\CarbonInterface;

if (! function_exists('localized_number')) {
    function localized_number(int|float|string|null $value, int $decimals = 0): string
    {
        return Localizer::number($value, $decimals);
    }
}

if (! function_exists('money')) {
    function money(int|float|string|null $value, int $decimals = 2): string
    {
        return Localizer::money($value, $decimals);
    }
}

if (! function_exists('localized_date')) {
    function localized_date(CarbonInterface|string|null $date, string $format = 'd M Y'): string
    {
        return Localizer::date($date, $format);
    }
}

if (! function_exists('localized_date_time')) {
    function localized_date_time(CarbonInterface|string|null $date, string $format = 'd M Y, h:i A'): string
    {
        return Localizer::dateTime($date, $format);
    }
}

if (! function_exists('default_label')) {
    function default_label(?string $value, string $group): string
    {
        return Localizer::label($value, $group);
    }
}

if (! function_exists('status_label')) {
    function status_label(?string $status): string
    {
        return Localizer::status($status);
    }
}
