<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\User;
use App\Support\Money;
use App\Support\Warranty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Багцаар бүртгэх: процессор, дэлгэц, mouse, keyboard, printer зэргийг нэг хуудсан дээр
 * нэг дор бүртгэж, хүсвэл ажилтанд шууд олгоно.
 */
class ComputerBulkController extends Controller
{
    /** Бүртгэлийн дугаар автоматаар үүсгэх угтвар (жишээ нь MS-20261019-001) */
    private const PREFIXES = Computer::INVENTORY_PREFIXES;

    /** Мөр бүрийн техникийн талбарууд (ангиллаас хамааран формд харагдана) */
    private const ROW_FIELDS = ['brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os', 'ip_address', 'mac_address', 'specs'];

    public function create(Request $request): View
    {
        return view('computers.bulk-create', [
            'employees' => User::query()->assignableBy($request->user())->orderBy('name')->get(),
            'departments' => Computer::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'selectedUserId' => $request->query('user_id'),
            'detectedRows' => $request->boolean('detected') ? $this->detectedRows($request) : [],
        ]);
    }

    /**
     * ?detected=1: «computer-burtgel.bat» скриптээр уншсан компьютер + залгаатай дэлгэц, printer-ийг мөр болгоно.
     * Mouse, keyboard-ийг скрипт таньж чаддаггүй тул хоосон мөр нэмж, гараар нөхүүлнэ.
     */
    private function detectedRows(Request $request): array
    {
        $pc = ComputerDetectController::normalize($request->query());
        $rows = [];

        if ($pc !== []) {
            $rows[] = ['category' => ($pc['type'] ?? null) === 'laptop' ? 'laptop' : 'processor', '_auto' => true]
                + array_intersect_key($pc, array_flip([...self::ROW_FIELDS, 'name']));
        }
        foreach (ComputerDetectController::normalizeDevices((array) $request->query('devices', [])) as $device) {
            $rows[] = $device + ['_auto' => true];
        }
        if ($rows !== []) {
            $present = array_column($rows, 'category');
            foreach (['mouse', 'keyboard'] as $cat) {
                if (! in_array($cat, $present, true)) {
                    $rows[] = ['category' => $cat];
                }
            }
        }

        return $rows;
    }

    public function store(Request $request): RedirectResponse
    {
        // Үнийг «450,000₮» гэж бичсэн ч тоо болгоно
        $request->merge(['rows' => array_map(
            fn ($row) => is_array($row) ? ['purchase_price' => Money::parse($row['purchase_price'] ?? null)] + $row : $row,
            (array) $request->input('rows', [])
        )]);

        // Баталгаа дуусах огноо хоосон бол худалдан авсан огноо + хугацаагаар бодно (JS ажиллаагүй үед)
        if (! $request->filled('warranty_until')) {
            $request->merge(['warranty_until' => Warranty::until($request->input('purchase_date'), $request->input('warranty_months'))]);
        }

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', User::assignableRule($request->user())],
            'branch_id' => [$request->user()->isSuperadmin() ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.category' => ['nullable', Rule::enum(EquipmentCategory::class)],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.inventory_number' => ['nullable', 'string', 'max:255'],
            'rows.*.brand' => ['nullable', 'string', 'max:255'],
            'rows.*.model' => ['nullable', 'string', 'max:255'],
            'rows.*.serial_number' => ['nullable', 'string', 'max:255'],
            'rows.*.cpu' => ['nullable', 'string', 'max:255'],
            'rows.*.ram_gb' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'rows.*.storage' => ['nullable', 'string', 'max:255'],
            'rows.*.os' => ['nullable', 'string', 'max:255'],
            'rows.*.ip_address' => ['nullable', 'ip'],
            'rows.*.mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
            'rows.*.specs' => ['nullable', 'string', 'max:500'],
            'rows.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'rows.*.purchase_price.numeric' => 'Үнийг тоогоор оруулна уу.',
            'warranty_until.after_or_equal' => 'Баталгаа дуусах огноо худалдан авсан огнооноос хойш байх ёстой.',
            'user_id.exists' => 'Сонгосон хэрэглэгч идэвхгүй эсвэл олдсонгүй.',
            'rows.*.ip_address.ip' => 'IP хаяг буруу байна.',
            'rows.*.mac_address.regex' => 'MAC хаяг AA:BB:CC:DD:EE:FF хэлбэртэй байх ёстой.',
            'rows.*.ram_gb.integer' => 'RAM бүхэл тоо байх ёстой.',
        ]);

        // Хоосон мөрийг (юу ч бөглөөгүй) алгасна, жишээ нь printer байхгүй бол
        $rows = [];
        foreach (array_values((array) $data['rows']) as $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row);

            $filled = collect(['name', 'purchase_price', ...self::ROW_FIELDS])
                ->contains(fn ($key) => filled($row[$key] ?? null));

            if ($filled) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return back()->withInput()->withErrors(['rows' => 'Дор хаяж нэг төхөөрөмжийн мэдээлэл (нэр, загвар эсвэл серийн дугаар) оруулна уу.']);
        }

        if ($errors = $this->duplicateErrors($rows)) {
            return back()->withInput()->withErrors(['rows' => $errors]);
        }

        $user = filled($data['user_id'] ?? null) ? User::findOrFail($data['user_id']) : null;
        $created = [];

        DB::transaction(function () use ($rows, $data, $user, $request, &$created) {
            $counters = [];
            $status = $this->byLabel(ComputerStatus::class, $user ? 'Ашиглагдаж байгаа' : 'Агуулахад');
            $main = null; // иж бүрдлийн үндсэн компьютер

            foreach ($this->kitOrder($rows) as $i => $row) {
                $category = EquipmentCategory::tryFrom((string) ($row['category'] ?? ''));
                $prefix = self::PREFIXES[$category?->value ?? 'other'] ?? 'EQ';

                // Иж бүрдлийн дагалдах хэрэгсэл бол дугаарыг model үүсгэнэ: PC-0012-1, PC-0012-2 ...
                $inventory = $main ? null : $this->nextInventoryNumber($prefix, $counters);

                // Нэр бичээгүй бол «Үйлдвэрлэгч Загвар», тэр нь ч байхгүй бол model «Ангилал Дугаар» гэж өгнө
                $name = filled($row['name'] ?? null)
                    ? $row['name']
                    : (trim(($row['brand'] ?? '').' '.($row['model'] ?? '')) ?: null);

                $value = fn (string $key) => filled($row[$key] ?? null) ? $row[$key] : null;

                $computer = new Computer([
                    'inventory_number' => $inventory,
                    'name' => $name,
                    'category' => $category,
                    'type' => $this->typeFor($category),
                    'brand' => $value('brand'),
                    'model' => $value('model'),
                    'serial_number' => $value('serial_number'),
                    'cpu' => $value('cpu'),
                    'ram_gb' => $value('ram_gb'),
                    'storage' => $value('storage'),
                    'os' => $value('os'),
                    'ip_address' => $value('ip_address'),
                    'mac_address' => $value('mac_address') ? strtoupper(str_replace('-', ':', $value('mac_address'))) : null,
                    'specs' => $value('specs'),
                    'purchase_price' => $value('purchase_price'),
                    'purchase_date' => $data['purchase_date'] ?? null,
                    'warranty_until' => $data['warranty_until'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null, // Админд model өөрийн салбарыг онооно
                    'department' => $data['department'] ?? null,
                    'location' => $data['location'] ?? null,
                    'status' => $status,
                    'assigned_user_id' => $user?->id, // model нь assigned_to-г синк хийнэ
                    'kit_parent_id' => $main?->id,
                ]);
                $computer->save();

                if ($i === 0 && $this->isKitMain($row)) {
                    $main = $computer;
                }

                if ($user) {
                    ComputerEvent::create([
                        'computer_id' => $computer->id,
                        'actor_id' => $request->user()->id,
                        'user_id' => $user->id,
                        'type' => 'assigned',
                        'note' => 'Багцаар бүртгэж олгосон',
                    ]);
                }

                $created[] = $computer;
            }
        });

        // Иж бүрдлийн үндсэн компьютер дагалдах хэрэгсэл нэмэгдэхэд -1 дугаар авсан тул шинэчилж уншина
        $created = array_map(fn (Computer $c) => $c->refresh(), $created);

        \App\Notifications\ComputersRegistered::dispatch($request->user(), $created);

        $names = collect($created)->map(fn ($c) => $c->inventory_number)->take(8)->implode(', ');
        $message = count($created).' төхөөрөмж бүртгэгдлээ ('.$names.(count($created) > 8 ? ' ...' : '').')'
            .($user ? ' ба '.$user->name.'-д олголоо.' : '.');

        return $user
            ? redirect()->route('computers.bulk-assign', ['user_id' => $user->id])->with('success', $message)
            : redirect()->route('computers.index')->with('success', $message);
    }

    /**
     * "Шинэ компьютер бүртгэх" формоос олон ангилал сонгосон үед: ангилал бүрээр тусдаа төхөөрөмж бүртгэнэ.
     * Нийтлэг мэдээлэл (нэр, төлөв, хэлтэс, байршил, хариуцагч, огноо, тэмдэглэл) бүгдэд хамаарна.
     * Төхөөрөмж бүрийн үзүүлэлт (items[i][brand], [model], [serial_number], [cpu] ...) тусдаа ирнэ;
     * ирээгүй бол (JS ажиллаагүй) зөвхөн ангиллаар бүртгэнэ.
     * Бүртгэлийн дугаар автоматаар үүснэ, нэр нь «Нэр · Ангилал» болно.
     */
    public function storeMultiple(Request $request): RedirectResponse
    {
        if (! $request->filled('warranty_until')) {
            $request->merge(['warranty_until' => Warranty::until($request->input('purchase_date'), $request->input('warranty_months'))]);
        }

        $data = $request->validate([
            'categories' => ['required_without:items', 'array', 'max:20'],
            'categories.*' => [Rule::enum(EquipmentCategory::class)],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.category' => ['required', Rule::enum(EquipmentCategory::class)],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.model' => ['nullable', 'string', 'max:255'],
            'items.*.serial_number' => ['nullable', 'string', 'max:255'],
            'items.*.cpu' => ['nullable', 'string', 'max:255'],
            'items.*.ram_gb' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'items.*.storage' => ['nullable', 'string', 'max:255'],
            'items.*.os' => ['nullable', 'string', 'max:255'],
            'items.*.ip_address' => ['nullable', 'ip'],
            'items.*.mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'],
            'items.*.specs' => ['nullable', 'string', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ComputerStatus::class)],
            'branch_id' => [$request->user()->isSuperadmin() ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', User::assignableRule($request->user())],
            'purchase_date' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'kit_parent_id' => ['nullable', 'integer', Rule::exists('computers', 'id')->whereNull('deleted_at')->whereNull('kit_parent_id')],
        ], [
            'categories.required_without' => 'Ангилал сонгоно уу.',
            'items.*.ip_address.ip' => 'IP хаяг буруу байна.',
            'items.*.mac_address.regex' => 'MAC хаяг AA:BB:CC:DD:EE:FF хэлбэртэй байх ёстой.',
            'items.*.ram_gb.integer' => 'RAM бүхэл тоо байх ёстой.',
            'assigned_user_id.exists' => 'Сонгосон хэрэглэгч идэвхгүй эсвэл олдсонгүй.',
            'warranty_until.after_or_equal' => 'Баталгаа дуусах огноо худалдан авсан огнооноос хойш байх ёстой.',
        ]);

        // Төхөөрөмж бүрийн мөр: items ирсэн бол түүгээр, үгүй бол ангилал бүрт хоосон мөр
        $items = array_values(array_map(
            fn ($row) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row),
            $data['items'] ?? array_map(fn ($c) => ['category' => $c], array_unique($data['categories'] ?? []))
        ));

        // enum-ийн дарааллаар эрэмбэлнэ (нэг ангиллын олон төхөөрөмж оруулсан дараалал хэвээр)
        $order = array_map(fn ($c) => $c->value, EquipmentCategory::cases());
        usort($items, fn ($a, $b) => array_search($a['category'], $order) <=> array_search($b['category'], $order));

        if ($errors = $this->duplicateErrors($items)) {
            return back()->withInput()->withErrors(['items' => $errors]);
        }

        $user = filled($data['assigned_user_id'] ?? null) ? User::findOrFail($data['assigned_user_id']) : null;

        $status = filled($data['status'] ?? null)
            ? ComputerStatus::from($data['status'])
            : $this->byLabel(ComputerStatus::class, $user ? 'Ашиглагдаж байгаа' : 'Агуулахад');

        $created = [];

        DB::transaction(function () use ($items, $data, $user, $status, $request, &$created) {
            $counters = [];
            $perCategory = array_count_values(array_column($items, 'category'));
            $seen = [];
            // Иж бүрдлийн үндсэн компьютер: формоос сонгосон бол бүгд түүний дагалдах хэрэгсэл болно
            $chosen = filled($data['kit_parent_id'] ?? null) ? Computer::find($data['kit_parent_id']) : null;
            $main = $chosen?->canBeKitMain() ? $chosen : null;
            $presetMain = $main !== null;

            foreach (($presetMain ? array_combine(range(1, count($items)), $items) : $this->kitOrder($items)) as $i => $item) {
                $category = EquipmentCategory::from($item['category']);
                // Иж бүрдлийн дагалдах хэрэгсэл бол дугаарыг model үүсгэнэ: PC-0012-1 ...
                $inventory = $main ? null : $this->nextInventoryNumber(self::PREFIXES[$category->value] ?? 'EQ', $counters);
                $value = fn (string $key) => filled($item[$key] ?? null) ? $item[$key] : null;

                // Нэр: «Нэр · Ангилал [2]», нэр бичээгүй бол «Үйлдвэрлэгч Загвар», эсвэл ангиллын нэр
                $label = $category->label();
                $seen[$category->value] = ($seen[$category->value] ?? 0) + 1;
                if ($perCategory[$category->value] > 1) {
                    $label .= ' '.$seen[$category->value];
                }
                $name = filled($data['name'] ?? null)
                    ? $data['name'].' · '.$label
                    : (trim(($value('brand') ?? '').' '.($value('model') ?? '')) ?: $label);

                $mac = $value('mac_address') ? strtoupper(str_replace('-', ':', $value('mac_address'))) : null;

                $computer = new Computer([
                    'inventory_number' => $inventory,
                    'name' => $name,
                    'category' => $category,
                    'type' => $this->typeFor($category),
                    'brand' => $value('brand'),
                    'model' => $value('model'),
                    'serial_number' => $value('serial_number'),
                    'cpu' => $value('cpu'),
                    'ram_gb' => $value('ram_gb'),
                    'storage' => $value('storage'),
                    'os' => $value('os'),
                    'ip_address' => $value('ip_address'),
                    'mac_address' => $mac,
                    'specs' => $value('specs'),
                    'branch_id' => $data['branch_id'] ?? null, // Админд model өөрийн салбарыг онооно
                    'department' => $data['department'] ?? null,
                    'location' => $data['location'] ?? null,
                    'status' => $status,
                    'assigned_user_id' => $user?->id, // model нь assigned_to-г синк хийнэ
                    'purchase_date' => $data['purchase_date'] ?? null,
                    'warranty_until' => $data['warranty_until'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'kit_parent_id' => $main?->id,
                ]);
                $computer->save();

                if ($i === 0 && $this->isKitMain($item)) {
                    $main = $computer;
                }

                if ($user) {
                    ComputerEvent::create([
                        'computer_id' => $computer->id,
                        'actor_id' => $request->user()->id,
                        'user_id' => $user->id,
                        'type' => 'assigned',
                        'note' => 'Олон ангиллаар бүртгэж олгосон',
                    ]);
                }

                $created[] = $computer;
            }
        });

        // Иж бүрдлийн үндсэн компьютер дагалдах хэрэгсэл нэмэгдэхэд -1 дугаар авсан тул шинэчилж уншина
        $created = array_map(fn (Computer $c) => $c->refresh(), $created);

        \App\Notifications\ComputersRegistered::dispatch($request->user(), $created);

        $names = collect($created)->map(fn ($c) => $c->inventory_number.' '.($c->category?->label() ?? ''))->implode(', ');
        $message = count($created).' төхөөрөмж бүртгэгдлээ ('.$names.')'.($user ? ' ба '.$user->name.'-д олголоо.' : '.');

        return $user
            ? redirect()->route('computers.bulk-assign', ['user_id' => $user->id])->with('success', $message)
            : redirect()->route('computers.index')->with('success', $message);
    }

    // ───────────────────────── туслах функцүүд ─────────────────────────

    /** Иж бүрдлийн үндсэн компьютер болох мөр: процессор эсвэл нөүтбүк */
    private function isKitMain(array $row): bool
    {
        return in_array((string) ($row['category'] ?? ''), Computer::KIT_MAIN_CATEGORIES, true);
    }

    /**
     * Иж бүрдэл: эхний процессор/нөүтбүк нь үндсэн компьютер болж хамгийн түрүүнд хадгалагдана,
     * бусад мөр (оруулсан дарааллаараа) түүний дагалдах хэрэгсэл болж дэд дугаар авна.
     * Компьютер байхгүй бол дараалал хэвээр, иж бүрдэл үүсэхгүй (0-р мөр үндсэн биш).
     */
    private function kitOrder(array $rows): array
    {
        $rows = array_values($rows);
        $mainIndex = collect($rows)->search(fn ($row) => $this->isKitMain($row));

        if ($mainIndex === false || count($rows) < 2) {
            // Иж бүрдэлгүй: isKitMain(0-р мөр) үнэн байж болох тул 0-р түлхүүрийг ашиглахгүй
            return array_combine(range(1, count($rows)), $rows);
        }

        $ordered = [$rows[$mainIndex]];
        foreach ($rows as $i => $row) {
            if ($i !== $mainIndex) {
                $ordered[] = $row;
            }
        }

        return $ordered; // 0-р түлхүүр = үндсэн компьютер
    }

    /** Давхардсан бүртгэлийн/серийн дугаарыг (мөрүүдийн дунд болон өгөгдлийн санд) шалгана */
    private function duplicateErrors(array $rows): array
    {
        $errors = [];

        foreach (['inventory_number' => 'Бүртгэлийн дугаар', 'serial_number' => 'Серийн дугаар'] as $field => $title) {
            $values = array_values(array_filter(array_column($rows, $field), fn ($v) => filled($v)));

            foreach (array_keys(array_filter(array_count_values($values), fn ($n) => $n > 1)) as $dup) {
                $errors[] = $title.' «'.$dup.'» нэг хуудсанд давхардсан байна.';
            }

            if ($values !== []) {
                $existing = Computer::global()->whereIn($field, $values)->pluck($field);
                foreach ($existing as $value) {
                    $errors[] = $title.' «'.$value.'» аль хэдийн бүртгэлтэй байна.';
                }
            }
        }

        return $errors;
    }

    /** Угтварын дараагийн дугаар (PC-20261019-001): тухайн өдрийн (устгагдсан ч) хамгийн их дугаараас нэгээр нэмнэ */
    private function nextInventoryNumber(string $prefix, array &$counters): string
    {
        $dated = Computer::datedPrefix($prefix);
        // Нэг хүсэлтэд олныг бүртгэхэд баазаас нэг л удаа уншаад, цааш нь санах ойд тоолно
        $counters[$dated] ??= Computer::maxInventorySuffix($dated);

        return sprintf('%s-%03d', $dated, ++$counters[$dated]);
    }

    /** Ангиллаас "Төрөл"-ийг тааруулна (процессор → Суурин, ноутбук → Зөөврийн, бусад → Бусад) */
    private function typeFor(?EquipmentCategory $category): ComputerType
    {
        $label = match ($category) {
            EquipmentCategory::Processor => 'Суурин компьютер',
            EquipmentCategory::Laptop => 'Зөөврийн компьютер',
            default => 'Бусад',
        };

        return $this->byLabel(ComputerType::class, $label, ComputerType::cases()[0]);
    }

    /** Enum-ийн case-ийг шошгоор нь олно (case-ийн нэрийг хатуу бичихгүйн тулд) */
    private function byLabel(string $enum, string $label, ?\BackedEnum $fallback = null): \BackedEnum
    {
        foreach ($enum::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return $fallback ?? throw new \RuntimeException("{$enum} дотор «{$label}» шошготой утга олдсонгүй.");
    }
}