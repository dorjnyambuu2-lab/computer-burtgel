<?php

namespace App\Enums;

/**
 * Тоног төхөөрөмжийн ангилал (процессор, дэлгэц, mouse, keyboard, printer ...).
 * Шинэ ангилал нэмэх бол case + label() + icon() гурван газар нэмнэ.
 */
enum EquipmentCategory: string
{
    case Processor = 'processor';
    case Laptop = 'laptop';
    case Monitor = 'monitor';
    case Mouse = 'mouse';
    case Keyboard = 'keyboard';
    case Printer = 'printer';
    case Scanner = 'scanner';
    case Ups = 'ups';
    case Network = 'network';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Processor => 'Процессор',
            self::Laptop => 'Нөүтбүк',
            self::Monitor => 'Дэлгэц',
            self::Mouse => 'Mouse',
            self::Keyboard => 'Keyboard',
            self::Printer => 'Printer',
            self::Scanner => 'Scanner',
            self::Ups => 'UPS',
            self::Network => 'Сүлжээний төхөөрөмж',
            self::Other => 'Бусад',
        };
    }

    /** SVG (24x24, stroke) дүрсний path */
    public function icon(): string
    {
        return match ($this) {
            self::Processor => 'M7 3h10v18H7zM10 7h4M12 17h.01',
            self::Laptop => 'M5 6h14v9H5zM3 19h18',
            self::Monitor => 'M4 5h16v11H4zM8 20h8M12 16v4',
            self::Mouse => 'M12 3a5 5 0 015 5v8a5 5 0 01-10 0V8a5 5 0 015-5zM12 3v6',
            self::Keyboard => 'M3 7h18v10H3zM7 11h.01M11 11h.01M15 11h.01M8 14h8',
            self::Printer => 'M7 8V3h10v5M7 17H4v-7h16v7h-3M7 14h10v7H7z',
            self::Scanner => 'M3 12h18M5 12v6h14v-6M7 12V8l10-3v7',
            self::Ups => 'M13 2L5 14h6l-1 8 8-12h-6z',
            self::Network => 'M12 20h.01M8.5 16.5a5 5 0 017 0M5 13a10 10 0 0114 0M1.5 9.5a15 15 0 0121 0',
            self::Other => 'M4 4h16v16H4zM9 9h6v6H9z',
        };
    }

    /**
     * Тухайн ангилалд хамаарах техникийн талбарууд. Жишээ нь дэлгэц, mouse-д CPU, RAM, ҮС, IP хэрэггүй.
     * Ангилалгүй (хуучин) бүртгэлд бүх талбар хамаарна.
     */
    public const SPEC_FIELDS = [
        // IP, MAC-г формд оруулахгүй: ажилтан нэвтрэхэд автоматаар бүртгэгдэнэ (App\Support\ClientNetwork)
        'computer' => ['brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os'],
        'network' => ['brand', 'model', 'serial_number', 'specs'],
        'basic' => ['brand', 'model', 'serial_number', 'specs'],
    ];

    /** Бүх техникийн талбар (ангилалгүй бүртгэлд бүгд хамаарна) */
    public const ALL_SPEC_FIELDS = ['brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os', 'specs'];

    public function specGroup(): string
    {
        return match ($this) {
            self::Processor, self::Laptop => 'computer',
            self::Printer, self::Scanner, self::Network => 'network',
            default => 'basic',
        };
    }

    /** @return list<string> */
    public static function specFieldsFor(?self $category): array
    {
        return $category ? self::SPEC_FIELDS[$category->specGroup()] : self::ALL_SPEC_FIELDS;
    }

    /** [утга => шошго] жагсаалт (select-д) */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}