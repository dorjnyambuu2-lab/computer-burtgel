<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** Бүрмөсөн устгахаас өмнөх бүх мэдээлэл (changes доторх түлхүүр) — түүхээс сэргээхэд ашиглана */
    public const SNAPSHOT = '_snapshot';

    public const ACTIONS = [
        'created' => 'Нэмсэн',
        'updated' => 'Зассан',
        'deleted' => 'Устгасан',
        'restored' => 'Сэргээсэн',
        'force_deleted' => 'Бүрмөсөн устгасан',
        'force_delete_approved' => 'Бүрмөсөн устгахыг зөвшөөрсөн',
        'logs_deleted' => 'Түүх устгасан',
        'restored_from_log' => 'Түүхээс сэргээсэн',
    ];

    public const ACTION_COLORS = [
        'created' => 'bg-emerald-100 text-emerald-700',
        'updated' => 'bg-indigo-100 text-indigo-700',
        'deleted' => 'bg-red-100 text-red-700',
        'restored' => 'bg-amber-100 text-amber-700',
        'force_deleted' => 'bg-slate-800 text-white',
        'force_delete_approved' => 'bg-rose-100 text-rose-700',
        'logs_deleted' => 'bg-slate-800 text-white',
        'restored_from_log' => 'bg-amber-100 text-amber-700',
    ];

    // Талбарын нэрийг монголоор харуулах (өөрийн баганын нэрээр засч болно)
    public const FIELD_LABELS = [
        'inventory_number' => 'Бүртгэлийн дугаар',
        'name' => 'Нэр',
        'type' => 'Төрөл',
        'brand' => 'Брэнд',
        'model' => 'Загвар',
        'serial_number' => 'Серийн дугаар',
        'cpu' => 'CPU',
        'ram_gb' => 'RAM (GB)',
        'ram_modules' => 'RAM модуль',
        'storage' => 'Диск',
        'os' => 'Үйлдлийн систем',
        'ip_address' => 'IP хаяг',
        'mac_address' => 'MAC хаяг',
        'department' => 'Алба',
        'unit' => 'Нэгж',
        'position' => 'Албан тушаал',
        'location' => 'Байршил',
        'assigned_to' => 'Хариуцагч',
        'holder_surname' => 'Овог',
        'holder_name' => 'Нэр',
        'status' => 'Төлөв',
        'purchase_date' => 'Худалдан авсан огноо',
        'purchase_price' => 'Үнэ (₮)',
        'warranty_until' => 'Баталгаа дуусах',
        'notes' => 'Тэмдэглэл',
        'repair_position' => 'Дарааллын байр',
        'repair_stage' => 'Засварын явц',
        'repair_queued_at' => 'Дараалалд орсон',
        'created_by' => 'Бүртгэсэн',
        'deleted_by' => 'Устгасан',
        'approved_by' => 'Зөвшөөрсөн Супер админ',
        'sequence_number' => 'Дэс дугаар',
        'specs' => 'Үзүүлэлт',
        'category' => 'Ангилал',
        'deleted_count' => 'Устгасан мөрийн тоо',
        'branch_id' => 'Салбар',
        'kit_parent_id' => 'Иж бүрдэл',
        'kit_index' => 'Иж бүрдлийн дугаар',
        'qr_code' => 'QR дугаар',
        'acknowledged_at' => 'Акт баталгаажсан',
        'network_updated_at' => 'Сүлжээ шинэчлэгдсэн',
    ];

    /** Түүхэнд харуулахгүй талбар (давхардсан эсвэл техникийн: хариуцагчийн нэр «Хариуцагч»-д байна) */
    // deleted_by: үйлдэл («Устгасан», «Сэргээсэн») болон «Хэн» баганаас аль хэдийн харагдана
    public const HIDDEN_FIELDS = ['assigned_user_id', 'ram_modules', 'updated_at', 'created_at', 'id', 'deleted_by'];

    /** Дэлгэрэнгүй цонхонд талбаруудыг бүлэглэх (бүлэгт ороогүй нь «Бусад») */
    public const FIELD_GROUPS = [
        'Үндсэн мэдээлэл' => ['inventory_number', 'name', 'category', 'type', 'status', 'sequence_number', 'qr_code', 'kit_parent_id', 'kit_index'],
        'Байршил ба хариуцагч' => ['branch_id', 'unit', 'department', 'position', 'location', 'holder_surname', 'holder_name', 'assigned_to', 'acknowledged_at'],
        'Техникийн үзүүлэлт' => ['brand', 'model', 'serial_number', 'cpu', 'ram_gb', 'storage', 'os', 'specs', 'ip_address', 'mac_address', 'network_updated_at'],
        'Худалдан авалт ба баталгаа' => ['purchase_date', 'purchase_price', 'warranty_until'],
        'Засвар' => ['repair_stage', 'repair_position', 'repair_queued_at'],
    ];

    /** Өөрчлөлтийн жагсаалтгүй үйлдлийн тайлбар (түүхийн «Өөрчлөлт» баганад) */
    public const ACTION_NOTES = [
        'deleted' => 'Устгасан хэсэг рүү шилжсэн',
        'restored' => 'Устгасан хэсгээс сэргээгдсэн',
        'force_deleted' => 'Бүрмөсөн устгагдсан',
        'restored_from_log' => 'Түүхээс сэргээгдсэн',
    ];

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    /**
     * Утгыг хүнд ойлгомжтой болгоно: «other» → «Бусад», «branch_id: 15» → салбарын нэр,
     * «2026-10-28 00:00:00» → «2026-10-28», «2500000» → «2,500,000₮». Хоосон бол «—».
     */
    public static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (! is_scalar($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        static $names = [];
        $label = match ($field) {
            'type' => \App\Enums\ComputerType::tryFrom((string) $value)?->label(),
            'status' => \App\Enums\ComputerStatus::tryFrom((string) $value)?->label(),
            'category' => \App\Enums\EquipmentCategory::tryFrom((string) $value)?->label(),
            'repair_stage' => \App\Enums\RepairStage::tryFrom((string) $value)?->label(),
            'branch_id' => $names['b'.$value] ??= Branch::query()->whereKey($value)->value('name'),
            'created_by', 'deleted_by', 'approved_by' => $names['u'.$value] ??= User::query()->whereKey($value)->value('name'),
            'purchase_price', 'cost' => is_numeric($value) ? \App\Support\Money::format($value) : null,
            'ram_gb' => is_numeric($value) ? $value.' GB' : null,
            default => null,
        };
        if ($label !== null) {
            return $label;
        }

        $text = is_bool($value) ? ($value ? 'Тийм' : 'Үгүй') : (string) $value;
        // Огноо: цаг нь 00:00:00 бол зөвхөн өдөр, ISO (2026-10-28T03:18:00.000000Z) бол «2026-10-28 03:18»
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $text)) {
            $date = \Illuminate\Support\Carbon::parse($text)->timezone(config('app.timezone'));

            return $date->format($date->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i');
        }

        return $text;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Бүрмөсөн устгасан бүртгэлийн устгахаас өмнөх мэдээлэл.
     * Шинэ түүхэнд бүтэн хуулбар (snapshot) хадгалагдсан; хуучинд нь «Нэмсэн» + «Зассан» түүхийг дарааллаар нь
     * давтаж сэргээнэ. Мэдээлэл олдохгүй бол null.
     */
    public function deletedSnapshot(): ?array
    {
        if ($this->action !== 'force_deleted' || ! $this->subject_type || ! $this->subject_id) {
            return null;
        }
        // Анхаар: модель дотор $this->changes нь Eloquent-ийн дотоод property тул баганыг getAttribute()-аар уншина
        $changes = (array) $this->getAttribute('changes');
        if (! empty($changes[self::SNAPSHOT])) {
            return $changes[self::SNAPSHOT];
        }

        $history = self::query()
            ->where('subject_type', $this->subject_type)->where('subject_id', $this->subject_id)
            ->whereIn('action', ['created', 'updated'])->where('id', '<', $this->id)
            ->orderBy('id')->get();

        if (! $history->contains('action', 'created')) {
            return null;
        }

        $state = [];
        foreach ($history as $entry) {
            foreach ((array) $entry->getAttribute('changes') as $field => $change) {
                if (is_array($change) && array_key_exists('new', $change)) {
                    $state[$field] = $change['new'];
                }
            }
        }

        // Нэр, бүртгэлийн дугаарын аль нь ч олдохгүй бол сэргээх хангалттай мэдээлэл алга
        return (filled($state['name'] ?? null) || filled($state['inventory_number'] ?? null)) ? $state : null;
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function actionColor(): string
    {
        return self::ACTION_COLORS[$this->action] ?? 'bg-slate-100 text-slate-600';
    }
}
