<?php

namespace App\Rules;

use App\Models\Computer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IP, MAC хаяг төхөөрөмж бүрт өөр байна: өөр (устгаагүй) компьютерт бүртгэлтэй бол хадгалахгүй.
 * MAC-г AA-BB / aa:bb бичлэгээс үл хамааран ижил гэж үзэж, санамсаргүй (random) MAC-г хүлээн авахгүй.
 */
class UniqueNetworkAddress implements ValidationRule
{
    public function __construct(private string $column, private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $isMac = $this->column === 'mac_address';
        if ($isMac) {
            if (Computer::isRandomMac($value)) {
                $fail('Энэ бол санамсаргүй (random) MAC — хожим өөрчлөгдөнө. Компьютерийн Wi-Fi тохиргооноос «Random hardware addresses»-ийг унтраагаад дахин авна уу.');

                return;
            }
            $value = Computer::normalizeMac($value) ?? $value;
        }

        $other = Computer::withoutGlobalScopes()->whereNull('deleted_at')
            ->where($this->column, $value)
            ->when($this->ignoreId, fn ($q) => $q->whereKeyNot($this->ignoreId))
            ->first(['id', 'sequence_number', 'name']);

        if ($other) {
            $fail(($isMac ? 'Энэ MAC' : 'Энэ IP')." хаяг «#{$other->sequence_number} {$other->name}» төхөөрөмжид бүртгэлтэй байна.");
        }
    }
}
