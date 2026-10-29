<?php

namespace App\Models\Concerns;

use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «department» текст ↔ department_id: хадгалахад салбарын хэлтэстэй тааруулна (байхгүй бол үүсгэнэ), текстийг
 * хэлтсийн нэрээр (том/жижиг үсгийг) жигдэлнэ. department_id-г шууд онооход текст нь хэлтсийн нэр болно.
 * Model-ийн booted()-ийн төгсгөлд registerDepartmentSync() дуудна: creating/updating нь бүх saving-ийн дараа,
 * бусад creating (салбар оноох) ажилласны дараа тааруулна.
 */
trait SyncsDepartment
{
    protected static function registerDepartmentSync(): void
    {
        $sync = function ($model) {
            if ($model->isDirty('department_id') && ! $model->isDirty('department')) {
                $model->department = Department::find($model->department_id)?->name;

                return;
            }
            if ($model->exists && ! $model->isDirty(['department', 'branch_id'])) {
                return;
            }
            $department = Department::resolve($model->branch_id, $model->department);
            $model->department_id = $department?->id;
            $model->department = $department?->name;
        };

        static::creating($sync);
        static::updating($sync);
    }

    /** Алба хэлтэс (department нэр нь текст атрибуттай давхцахгүйн тулд) */
    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
