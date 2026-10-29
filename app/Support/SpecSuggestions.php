<?php

namespace App\Support;

use App\Enums\EquipmentCategory;
use App\Models\Computer;

/**
 * Техникийн үзүүлэлтийн санал болгох утгууд (үйлдвэрлэгч, загвар, CPU, RAM, диск, ҮС, дэлгэцийн хэмжээ ...).
 * Талбарт жагсаалтаас сонгоно, байхгүй бол гараар бичнэ. «Шинэ компьютер бүртгэх» форм ашиглана.
 */
class SpecSuggestions
{
    /** Компьютерийн (процессор, нөүтбүк) үйлдвэрлэгч ба загварууд */
    public const MODELS_BY_BRAND = [
        'Dell' => ['OptiPlex 3000', 'OptiPlex 5000', 'OptiPlex 7000', 'Vostro 3520', 'Latitude 5440', 'Latitude 7440', 'Inspiron 15 3520', 'Precision 3660', 'PowerEdge R650'],
        'HP' => ['ProDesk 400 G9', 'EliteDesk 800 G9', 'ProBook 450 G10', 'EliteBook 840 G10', 'HP 250 G9', 'Pavilion 15', 'ProLiant DL380 Gen10'],
        'Lenovo' => ['ThinkCentre M70s', 'ThinkCentre M90q', 'ThinkPad E14', 'ThinkPad T14', 'ThinkPad X1 Carbon', 'IdeaPad 3', 'IdeaPad 5', 'ThinkSystem SR650'],
        'Asus' => ['VivoBook 15', 'ExpertBook B1', 'ZenBook 14', 'ROG Strix', 'ExpertCenter D5'],
        'Acer' => ['Aspire 3', 'Aspire 5', 'Veriton X', 'TravelMate P2', 'Nitro 5'],
        'Apple' => ['MacBook Air M2', 'MacBook Air M3', 'MacBook Pro 14', 'iMac 24', 'Mac mini'],
        'MSI' => ['Modern 15', 'Katana 15', 'PRO DP21'],
        'Microsoft' => ['Surface Laptop 5', 'Surface Pro 9'],
        'Samsung' => ['Galaxy Book3'],
        'Huawei' => ['MateBook D15', 'MateBook 14'],
        'Xiaomi' => ['RedmiBook 15'],
        'Gigabyte' => [],
        'Угсармал' => [],
    ];

    public const CPU = [
        'Intel Core i3-10100', 'Intel Core i3-12100', 'Intel Core i3-1215U', 'Intel Core i5-10400', 'Intel Core i5-11400',
        'Intel Core i5-12400', 'Intel Core i5-13400', 'Intel Core i5-1235U', 'Intel Core i5-1335U', 'Intel Core i7-10700',
        'Intel Core i7-12700', 'Intel Core i7-13700', 'Intel Core i7-1255U', 'Intel Core i9-13900', 'Intel Core Ultra 5 125U',
        'Intel Core Ultra 7 155H', 'Intel Pentium Gold G6400', 'Intel Celeron N4500', 'Intel Xeon E-2336', 'Intel Xeon Silver 4314',
        'AMD Ryzen 3 5300U', 'AMD Ryzen 5 5600G', 'AMD Ryzen 5 5625U', 'AMD Ryzen 5 7530U', 'AMD Ryzen 7 5700G',
        'AMD Ryzen 7 5825U', 'AMD Ryzen 7 7735HS', 'AMD Ryzen 9 7900', 'Apple M1', 'Apple M2', 'Apple M3',
    ];

    public const RAM = [
        '4GB', '8GB', '16GB', '32GB', '64GB',
        '4GB + 4GB', '8GB + 4GB', '8GB + 8GB', '16GB + 8GB', '16GB + 16GB', '32GB + 32GB',
    ];

    // Маягтын RAM модулийн сонголт (модуль бүрийг «+» товчоор нэмнэ)
    public const RAM_SIZES = ['2GB', '4GB', '8GB', '16GB', '32GB', '64GB'];

    /**
     * RAM: «8», «8GB», «8GB + 4GB», «8+4» → [нийт GB, модулиуд]: [12, '8GB + 4GB'].
     * Ганц модультай бол модулиуд null. Буруу бичсэн бол null.
     *
     * @return array{0: ?int, 1: ?string}|null
     */
    public static function ram(mixed $value): ?array
    {
        if ($value === null || trim((string) $value) === '') {
            return [null, null];
        }

        $sizes = [];
        foreach (preg_split('/\s*\+\s*/', trim((string) $value)) as $part) {
            if (! preg_match('/^(\d+)\s*(?:GB)?$/i', $part, $m)) {
                return null;
            }
            $sizes[] = (int) $m[1];
        }

        return [array_sum($sizes), count($sizes) > 1 ? implode(' + ', array_map(fn ($n) => $n.'GB', $sizes)) : null];
    }

    /** RAM талбарын шалгалт: «8» эсвэл «8GB + 4GB» */
    public static function ramRule(): array
    {
        return ['nullable', function ($attribute, $value, $fail) {
            $ram = self::ram($value);
            if ($ram === null || $ram[0] < 1 || $ram[0] > 65535) {
                $fail('RAM-ыг «8GB» эсвэл «8GB + 4GB» хэлбэрээр бичнэ үү.');
            }
        }];
    }

    public const STORAGE = [
        '128GB SSD', '256GB SSD', '512GB SSD', '1TB SSD', '2TB SSD', '256GB NVMe SSD', '512GB NVMe SSD', '1TB NVMe SSD',
        '320GB HDD', '500GB HDD', '1TB HDD', '2TB HDD', '4TB HDD',
        '256GB SSD + 1TB HDD', '512GB SSD + 1TB HDD', '512GB SSD + 2TB HDD', '1TB SSD + 2TB HDD',
    ];

    // Маягтын SSD, HDD талбарын сонголт (хэмжээ; «NVMe» гэж нэмж бичиж болно)
    public const SSD_SIZES = ['128GB', '240GB', '256GB', '480GB', '512GB', '1TB', '2TB', '256GB NVMe', '512GB NVMe', '1TB NVMe', '2TB NVMe'];

    public const HDD_SIZES = ['320GB', '500GB', '1TB', '2TB', '4TB'];

    public const M2_SIZES = ['128GB', '256GB', '512GB', '1TB', '2TB', '256GB NVMe', '512GB NVMe', '1TB NVMe'];

    /** Дискийн төрөл: маягтын талбар => нэгтгэсэн мөрөнд бичигдэх нэр */
    public const DISK_KINDS = ['ssd' => 'SSD', 'hdd' => 'HDD', 'm2' => 'M.2'];

    /** Дискийн хэсгийн төрөл: «256GB M.2» → m2, «1TB HDD» → hdd, бусад → ssd */
    private static function diskKind(string $part): string
    {
        return preg_match('/\bM\.?2\b/i', $part) ? 'm2' : (preg_match('/\bHDD\b/i', $part) ? 'hdd' : 'ssd');
    }

    /** «512GB SSD + 1TB HDD + 256GB M.2» → ['ssd' => '512GB', 'hdd' => '1TB', 'm2' => '256GB'] (танигдаагүй дискийг SSD талбарт) */
    public static function storageParts(?string $value): array
    {
        $parts = array_fill_keys(array_keys(self::DISK_KINDS), []);
        foreach (preg_split('/\s*\+\s*/', trim((string) self::storage($value))) as $part) {
            if ($part === '') {
                continue;
            }
            $kind = self::diskKind($part);
            $label = $kind === 'm2' ? 'M\.?2' : $kind;
            $parts[$kind][] = trim(preg_replace('/\s*\b'.$label.'\b/i', '', $part));
        }

        return array_map(fn ($list) => implode(' + ', $list), $parts);
    }

    /** SSD, HDD, M.2 талбараас нэг мөр: ('512', '1TB', '256GB') → «512GB SSD + 1TB HDD + 256GB M.2» */
    public static function composeStorage(?string $ssd, ?string $hdd, ?string $m2 = null): ?string
    {
        $out = [];
        foreach (['SSD' => $ssd, 'HDD' => $hdd, 'M.2' => $m2] as $kind => $value) {
            foreach (preg_split('/\s*\+\s*/', trim((string) $value)) as $part) {
                if ($part === '') {
                    continue;
                }
                if (preg_match('/^\d+(?:[.,]\d+)?$/', $part)) {
                    $part .= 'GB';
                }
                $out[] = preg_match('/\b(SSD|HDD|Disk|M\.?2)\b/i', $part) ? $part : $part.' '.$kind;
            }
        }

        return $out ? self::storage(implode(' + ', $out)) : null;
    }

    /** Нийт дискийн хэмжээ GB-ээр (1TB = 1024GB): «512GB SSD + 1TB HDD» → 1536. Хэмжээ танигдахгүй бол null */
    public static function storageTotalGb(?string $value): ?float
    {
        $total = 0.0;
        $found = false;
        foreach (preg_split('/\s*\+\s*/', trim((string) $value)) as $part) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|TB)/i', $part, $m)) {
                $total += (float) str_replace(',', '.', $m[1]) * (strtoupper($m[2]) === 'TB' ? 1024 : 1);
                $found = true;
            }
        }

        return $found ? $total : null;
    }

    /** Харуулах: олон диск бол нийт хэмжээтэй — «512GB SSD + 1TB HDD (нийт 1536 GB)» */
    public static function storageLabel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $total = count(preg_split('/\s*\+\s*/', trim($value))) > 1 ? self::storageTotalGb($value) : null;

        return $total !== null ? $value.' (нийт '.rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.').' GB)' : $value;
    }
    /**
     * Дискийг «хэмжээ + төрөл» хэлбэрт: «SSD 512GB» → «512GB SSD», «ssd 512 gb + hdd 1tb» → «512GB SSD + 1TB HDD».
     * Танигдахгүй хэсгийг байгаагаар нь үлдээнэ.
     */
    public static function storage(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        return collect(preg_split('/\s*\+\s*/', trim($value)))->map(function (string $part) {
            if (! preg_match('/^(?:(.*?)\s*)?(\d+(?:[.,]\d+)?)\s*(GB|TB)(?:\s*(.*))?$/iu', $part, $m)) {
                return $part;
            }
            $kind = trim(($m[1] ?? '').' '.($m[4] ?? ''));
            $kind = preg_replace_callback('/\b(ssd|hdd|nvme|sata|m\.2)\b/i', fn ($k) => strtolower($k[1]) === 'nvme' ? 'NVMe' : strtoupper($k[1]), $kind);

            return trim(str_replace(',', '.', $m[2]).strtoupper($m[3]).' '.$kind);
        })->implode(' + ');
    }

    public const OS = [
        'Windows 11 Pro', 'Windows 11 Home', 'Windows 10 Pro', 'Windows 10 Home', 'Windows Server 2022', 'Windows Server 2019',
        'Ubuntu 22.04 LTS', 'Ubuntu 24.04 LTS', 'macOS', 'Linux', 'Үйлдлийн системгүй',
    ];

    /** Компьютерээс бусад төхөөрөмжийн үйлдвэрлэгчид */
    public const ITEM_BRANDS = [
        'monitor' => ['Dell', 'HP', 'Samsung', 'LG', 'AOC', 'Philips', 'BenQ', 'Lenovo', 'Asus', 'Acer', 'ViewSonic', 'Xiaomi'],
        'mouse' => ['Logitech', 'A4Tech', 'Microsoft', 'Dell', 'HP', 'Lenovo', 'Rapoo', 'Genius', 'Razer'],
        'keyboard' => ['Logitech', 'A4Tech', 'Microsoft', 'Dell', 'HP', 'Lenovo', 'Rapoo', 'Genius', 'Razer'],
        'printer' => ['HP', 'Canon', 'Epson', 'Brother', 'Xerox', 'Kyocera', 'Ricoh', 'Pantum', 'Samsung'],
        'scanner' => ['Canon', 'Epson', 'HP', 'Fujitsu', 'Brother', 'Kodak', 'Plustek'],
        'ups' => ['APC', 'Eaton', 'CyberPower', 'Vertiv', 'PowerCom', 'Prolink', 'Delta'],
        'network' => ['Cisco', 'TP-Link', 'MikroTik', 'Ubiquiti', 'D-Link', 'Huawei', 'Netgear', 'Ruijie', 'Fortinet'],
        'other' => [],
    ];

    /** Компьютерээс бусад төхөөрөмжийн үзүүлэлт (specs багана) */
    public const ITEM_SPECS = [
        'monitor' => ['19 инч', '21.5 инч', '22 инч', '23.8 инч', '24 инч, 1920x1080', '27 инч, 1920x1080', '27 инч, 2560x1440', '32 инч'],
        'mouse' => ['Утастай, USB', 'Утасгүй, USB', 'Bluetooth'],
        'keyboard' => ['Утастай, USB', 'Утасгүй, USB', 'Bluetooth', 'Гар + mouse иж бүрдэл'],
        'printer' => ['Лазер, хар цагаан, A4', 'Лазер, өнгөт, A4', 'Бэхэн, өнгөт, A4', 'Олон үйлдэлт (MFP), A4', 'A3'],
        'scanner' => ['A4, USB', 'A4, сүлжээний', 'A3'],
        'ups' => ['600VA', '800VA', '1000VA', '1500VA', '2000VA', '3000VA'],
        'network' => ['Switch 8 порт', 'Switch 16 порт', 'Switch 24 порт', 'Switch 48 порт', 'Router', 'Wi-Fi Access Point', 'Firewall'],
        'other' => [],
    ];

    public const SPECS_PLACEHOLDER = [
        'monitor' => '24 инч, 1920x1080', 'mouse' => 'Утастай / утасгүй, USB', 'keyboard' => 'Утастай / утасгүй, USB',
        'printer' => 'Лазер, хар цагаан, A4', 'scanner' => 'A4, USB', 'ups' => '1000VA / 600W', 'network' => 'Switch 24 порт',
        'other' => 'Нэмэлт мэдээлэл',
    ];

    public const LABELS = [
        'brand' => 'Үйлдвэрлэгч', 'model' => 'Загвар', 'serial_number' => 'Серийн дугаар', 'cpu' => 'Процессор (CPU)',
        'ram_gb' => 'RAM (GB)', 'storage' => 'Санах ой (диск)', 'os' => 'Үйлдлийн систем', 'ip_address' => 'IP хаяг',
        'mac_address' => 'MAC хаяг', 'specs' => 'Үзүүлэлт',
    ];

    /** Компьютерийн бүлгийн ангилал (ангилалгүй бүртгэл ч энд орно) */
    private const COMPUTER_CATEGORIES = ['processor', 'laptop'];

    /** Гараар бичсэн утгыг жагсаалтын хэлбэрт оруулах талбарууд («dell» → «Dell») */
    public const LEARNED_FIELDS = ['brand', 'model', 'cpu', 'storage', 'os', 'specs', 'department', 'unit', 'position', 'location'];

    private static ?array $suggest = null;
    private static ?array $modelsByBrand = null;
    private static array $byCategory = [];
    private static ?array $canonical = null;

    /**
     * Компьютерийн талбаруудын санал: үндсэн жагсаалт + гараар оруулж хадгалсан утгууд (DB).
     * Хадгалсан бүртгэлийн утга автоматаар жагсаалтад нэмэгдэнэ (устгасан бүртгэлийнх ч үлдэнэ).
     *
     * @return array<string, list<string>>
     */
    public static function suggest(): array
    {
        return self::$suggest ??= [
            'brand' => self::merge(array_keys(self::MODELS_BY_BRAND), self::learned('brand', self::COMPUTER_CATEGORIES)),
            'model' => self::merge(array_merge(...array_values(self::MODELS_BY_BRAND)), self::learned('model', self::COMPUTER_CATEGORIES)),
            'cpu' => self::merge(self::CPU, self::learned('cpu')),
            'ram_gb' => self::RAM,
            'storage' => self::merge(self::STORAGE, self::learned('storage')),
            'os' => self::merge(self::OS, self::learned('os')),
            'department' => self::learned('department'),
            'unit' => self::learned('unit'),
            'position' => self::learned('position'),
            'location' => self::learned('location'),
        ];
    }

    /** Үйлдвэрлэгч бүрийн загварууд: үндсэн + хадгалсан (компьютер) */
    public static function modelsByBrand(): array
    {
        if (self::$modelsByBrand !== null) {
            return self::$modelsByBrand;
        }

        $map = self::MODELS_BY_BRAND;
        try {
            Computer::withTrashed()->whereNotNull('brand')->where('brand', '!=', '')->whereNotNull('model')->where('model', '!=', '')
                ->where(fn ($q) => $q->whereIn('category', self::COMPUTER_CATEGORIES)->orWhereNull('category'))
                ->distinct()->limit(500)->get(['brand', 'model'])
                ->each(function ($row) use (&$map) {
                    $key = collect(array_keys($map))->first(fn ($b) => mb_strtolower($b) === mb_strtolower($row->brand)) ?? $row->brand;
                    $map[$key] = self::merge($map[$key] ?? [], [$row->model]);
                });
        } catch (\Throwable) {
            // DB алдаа гарвал үндсэн жагсаалт
        }

        return self::$modelsByBrand = $map;
    }

    /** Компьютерээс бусад ангиллын үйлдвэрлэгч: үндсэн + хадгалсан */
    public static function itemBrands(string $category): array
    {
        return self::$byCategory['brand'][$category] ??= self::merge(self::ITEM_BRANDS[$category] ?? [], self::learned('brand', [$category]));
    }

    /** Компьютерээс бусад ангиллын загвар: хадгалсан утгууд (жишээ нь дэлгэцийн P2419H) */
    public static function itemModels(string $category): array
    {
        return self::$byCategory['model'][$category] ??= self::learned('model', [$category]);
    }

    /** Ангиллын «Үзүүлэлт»: үндсэн + хадгалсан (жишээ нь «34 инч, curved») */
    public static function itemSpecs(string $category): array
    {
        return self::$byCategory['specs'][$category] ??= self::merge(self::ITEM_SPECS[$category] ?? [], self::learned('specs', [$category]));
    }

    /**
     * Хадгалахын өмнө: илүү зайг арилгаж, жагсаалтад байгаа утгатай үсгийн том жижгээрээ л ялгаатай бол
     * жагсаалтын хэлбэрийг авна («dell» → «Dell»). Ингэснээр жагсаалтад давхардал үүсэхгүй.
     */
    public static function canonicalize(Computer $computer): void
    {
        foreach (self::LEARNED_FIELDS as $field) {
            $value = $computer->getAttribute($field);
            if (! is_string($value) || ! $computer->isDirty($field)) {
                continue;
            }

            $value = trim(preg_replace('/\s+/u', ' ', $value));
            if ($value === '') {
                $computer->setAttribute($field, null);
                continue;
            }

            $map = self::canonicalMap();
            $key = mb_strtolower($value);
            $computer->setAttribute($field, $map[$field][$key] ?? $value);

            // Нэг хүсэлтэд олныг бүртгэхэд (багцаар) дараагийн мөрүүд ч энэ хэлбэрийг авна
            self::$canonical[$field][$key] ??= $value;
        }
    }

    /** Шинэ утга хадгалагдсаны дараа жагсаалтуудыг дахин уншина */
    public static function flush(): void
    {
        self::$suggest = self::$modelsByBrand = null;
        self::$byCategory = [];
    }

    /** [талбар => [жижиг үсгээр => жагсаалтын хэлбэр]] */
    private static function canonicalMap(): array
    {
        if (self::$canonical !== null) {
            return self::$canonical;
        }

        $lists = self::suggest();
        $lists['brand'] = array_merge($lists['brand'], ...array_values(self::ITEM_BRANDS));
        $lists['specs'] = array_merge(...array_values(self::ITEM_SPECS));
        $lists['brand'] = array_merge($lists['brand'], self::learned('brand'));
        $lists['model'] = array_merge($lists['model'], self::learned('model'));
        $lists['specs'] = array_merge($lists['specs'], self::learned('specs'));

        $map = [];
        foreach (self::LEARNED_FIELDS as $field) {
            foreach ($lists[$field] ?? [] as $v) {
                $map[$field][mb_strtolower($v)] ??= $v;
            }
        }

        return self::$canonical = $map;
    }

    /** Бүртгэлүүдэд (устгасан ч) хадгалагдсан ялгаатай утгууд, ангиллаар шүүж болно */
    private static function learned(string $field, ?array $categories = null): array
    {
        try {
            return Computer::withTrashed()
                ->whereNotNull($field)->where($field, '!=', '')
                ->when($categories !== null, fn ($q) => $q->where(function ($q) use ($categories) {
                    $q->whereIn('category', $categories);
                    // Ангилалгүй бүртгэлийг компьютер гэж үзнэ
                    if (array_intersect($categories, self::COMPUTER_CATEGORIES)) {
                        $q->orWhereNull('category');
                    }
                }))
                ->distinct()->orderBy($field)->limit(300)
                ->pluck($field)->map(fn ($v) => (string) $v)->all();
        } catch (\Throwable) {
            return []; // DB алдаа гарвал зөвхөн үндсэн жагсаалт
        }
    }

    /** Үндсэн жагсаалтын ард шинэ утгуудыг нэмнэ (үсгийн том жижгийг үл харгалзан давхардуулахгүй) */
    private static function merge(array $base, array $extra): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($base, $extra) as $v) {
            $v = (string) $v;
            $k = mb_strtolower(trim($v));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $v;
        }

        return $out;
    }

    /**
     * Төхөөрөмжийн карт/мөрөнд гарах талбарууд ангиллаас хамаарна (JS-д дамжуулна).
     */
    public static function itemConfig(): array
    {
        return [
            'labels' => self::LABELS,
            'groups' => EquipmentCategory::SPEC_FIELDS,
            'groupOf' => collect(EquipmentCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->specGroup()])->all(),
            'specsPlaceholder' => self::SPECS_PLACEHOLDER,
        ];
    }
}
