<?php

namespace App\Enums;

enum ComputerType: string
{
    case Desktop = 'desktop';
    case Laptop = 'laptop';
    case AllInOne = 'all_in_one';
    case Server = 'server';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Desktop => 'Суурин компьютер',
            self::Laptop => 'Зөөврийн компьютер',
            self::AllInOne => 'All-in-One',
            self::Server => 'Сервер',
            self::Other => 'Бусад',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
