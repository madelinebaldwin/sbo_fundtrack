<?php

namespace App\Support;

final class Currency
{
    public static function format(float|int|string $amount): string
    {
        return '₱'.number_format((float) $amount, 2, '.', ',');
    }
}
