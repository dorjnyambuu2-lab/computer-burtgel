<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Модель дээр `use LogsActivity;` гэж нэмэхэд нэмэх / засах / устгах / сэргээх
 * үйлдэл бүр activity_logs хүснэгтэд автоматаар бичигдэнэ.
 * Controller-ийн код өөрчлөх шаардлагагүй.
 *
 * @method static void created(\Closure|string $callback)
 * @method static void updated(\Closure|string $callback)
 * @method static void deleted(\Closure|string $callback)
 * @method static void restored(\Closure|string $callback)
 */
trait LogsActivity
{
    /** Лог руу бичихгүй талбарууд */
    protected static array $activityIgnore = [
        'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token',
    ];

    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $new = [];
            foreach ($model->getAttributes() as $field => $value) {
                if (in_array($field, static::$activityIgnore, true) || $value === null || $value === '') {
                    continue;
                }
                $new[$field] = ['new' => $value];
            }
            $model->recordActivity('created', $new);
        });

        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $value) {
                if (in_array($field, static::$activityIgnore, true)) {
                    continue;
                }
                $changes[$field] = [
                    'old' => $model->getRawOriginal($field),
                    'new' => $value,
                ];
            }
            if ($changes) {
                $model->recordActivity('updated', $changes);
            }
        });

        static::deleted(function ($model) {
            $force = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
            // Бүрмөсөн устгахад бүх мэдээллийг түүхэнд хадгална: Супер админ түүхээс буцааж сэргээж болно
            $model->recordActivity($force ? 'force_deleted' : 'deleted', $force ? [ActivityLog::SNAPSHOT => $model->getAttributes()] : null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => $model->recordActivity('restored'));
        }
    }

    public function recordActivity(string $action, ?array $changes = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'changes' => $changes ?: null,
        ]);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('id');
    }

    /** Жагсаалтад харагдах нэр. Өөр багана ашиглах бол энд нэмнэ. */
    public function activityLabel(): string
    {
        foreach (['name', 'hostname', 'serial_number', 'serial', 'inventory_number'] as $field) {
            $value = $this->getAttribute($field);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }
}
