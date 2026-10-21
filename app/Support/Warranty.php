<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Баталгааны хугацаа: «Баталгаа дуусах огноо» = худалдан авсан огноо + хугацаа (сараар) */
class Warranty
{
    /** [сар => шошго] */
    public const OPTIONS = [6 => '6 сар', 12 => '1 жил', 24 => '2 жил', 36 => '3 жил', 60 => '5 жил'];

    /** Худалдан авсан огноо болон хугацаанаас дуусах огноог бодно (Y-m-d). Бодох боломжгүй бол null. */
    public static function until(mixed $purchaseDate, mixed $months): ?string
    {
        if (blank($purchaseDate) || ! is_numeric($months) || (int) $months <= 0) {
            return null;
        }

        try {
            return Carbon::parse($purchaseDate)->addMonthsNoOverflow((int) $months)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
