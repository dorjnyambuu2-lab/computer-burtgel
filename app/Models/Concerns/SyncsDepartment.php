<?php

namespace App\Models\Concerns;

use App\Models\Department;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «department», «unit» текст ↔ department_id, unit_id: хадгалахад салбарын алба, нэгжтэй тааруулна (байхгүй бол үүсгэнэ),
 * текстийг жагсаалтын нэрээр (том/жижиг үсгийг) жигдэлнэ. *_id-г шууд онооход текст нь жагсаалтын нэр болно.
 * Model-ийн booted()-ийн төгсгөлд registerDepartmentSync() дуудна: creating/updating нь бүх saving-ийн дараа,
 * бусад creating (салбар оноох) ажилласны дараа тааруулна.
 *
 * @method static void creating(\Closure|string $callback)
 * @method static void updating(\Closure|string $callback)
 */
trait SyncsDepartment
{
    /** [текст багана => жагсаалтын model] */
    private const BRANCH_LISTS = ['department' => Department::class, 'unit' => Unit::class];

    protected static function registerDepartmentSync(): void
    {
        $sync = function ($model) {
            foreach (self::BRANCH_LISTS as $column => $class) {
                $idColumn = $column.'_id';
                if ($model->isDirty($idColumn) && ! $model->isDirty($column)) {
                    $model->$column = $class::find($model->$idColumn)?->name;

                    continue;
                }
                if ($model->exists && ! $model->isDirty([$column, 'branch_id'])) {
                    continue;
                }
                $record = $class::resolve($model->branch_id, $model->$column);
                $model->$idColumn = $record?->id;
                $model->$column = $record?->name;
            }
        };

        static::creating($sync);
        static::updating($sync);
    }

    /** Алба хэлтэс (department нэр нь текст атрибуттай давхцахгүйн тулд) */
    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Нэгж (unit нэр нь текст атрибуттай давхцахгүйн тулд) */
    public function unitRecord(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
