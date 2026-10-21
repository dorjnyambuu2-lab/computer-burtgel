<?php

namespace App\Enums;

enum ComputerStatus: string
{
    case New = 'new';
    case Active = 'active';
    case InRepair = 'in_repair';
    case InStorage = 'in_storage';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Шинэ',
            self::Active => 'Ашиглагдаж байгаа',
            self::InRepair => 'Засварт',
            self::InStorage => 'Агуулахад',
            self::Decommissioned => 'Актласан',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-violet-100 text-violet-700',
            self::Active => 'bg-emerald-100 text-emerald-700',
            self::InRepair => 'bg-amber-100 text-amber-700',
            self::InStorage => 'bg-sky-100 text-sky-700',
            self::Decommissioned => 'bg-slate-200 text-slate-600',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
