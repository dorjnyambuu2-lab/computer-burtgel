<?php

namespace App\Enums;

/** Засварт байгаа компьютерийн явц (засвар дуусахад дарааллаас гарна) */
enum RepairStage: string
{
    case Queued = 'queued';
    case Diagnosing = 'diagnosing';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Дараалалд',
            self::Diagnosing => 'Оношилгоо',
            self::InProgress => 'Засаж байна',
            self::WaitingParts => 'Сэлбэг хүлээж буй',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Queued => 'bg-slate-100 text-slate-700',
            self::Diagnosing => 'bg-sky-100 text-sky-700',
            self::InProgress => 'bg-blue-100 text-blue-700',
            self::WaitingParts => 'bg-violet-100 text-violet-700',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
