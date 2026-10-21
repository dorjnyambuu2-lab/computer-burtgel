<?php

namespace App\Support;

/** Мөнгөн дүнг төгрөгөөр харуулах: 1250000 → «1,250,000₮» */
class Money
{
    public static function format(int|float|string|null $amount, string $empty = '—'): string
    {
        if ($amount === null || $amount === '') {
            return $empty;
        }

        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return number_format($amount, $decimals, '.', ',').'₮';
    }

    /**
     * Формоос ирсэн дүнг тоо болгоно: «1,250,000», «1 250 000₮» → 1250000. Хоосон бол null.
     */
    public static function parse(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/[\s,₮]/u', '', (string) $value);

        return $clean === '' ? null : $clean;
    }
}
