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
        'storage' => 'Диск',
        'os' => 'Үйлдлийн систем',
        'ip_address' => 'IP хаяг',
        'mac_address' => 'MAC хаяг',
        'department' => 'Алба хэлтэс',
        'location' => 'Байршил',
        'assigned_to' => 'Хариуцагч',
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
    ];

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
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
