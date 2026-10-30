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
    /** [текст багана => жагсаалтын model]; нэгж эхэлж (шинэ алба сонгосон нэгжид харьяалагдана) */
    private const BRANCH_LISTS = ['unit' => Unit::class, 'department' => Department::class];

    protected static function registerDepartmentSync(): void
    {
        $sync = function ($model) {
            foreach (self::BRANCH_LISTS as $column => $class) {
                $idColumn = $column.'_id';
                if ($model->isDirty($idColumn) && ! $model->isDirty($column)) {
                    $record = $class::find($model->$idColumn);
                    $model->$column = $record?->name;
                } elseif ($model->exists && ! $model->isDirty([$column, 'branch_id'])
                    // Тексттэй боловч холбоос нь тасарсан бол хадгалахад дахин холбоно
                    && ! ($model->$idColumn === null && filled($model->$column))) {
                    continue;
                } else {
                    // Шинээр үүсэх алба: сонгосон нэгжид харьяалагдана
                    $record = $class::resolve($model->branch_id, $model->$column, $column === 'department' ? ['unit_id' => $model->unit_id] : []);
                    $model->$idColumn = $record?->id;
                    $model->$column = $record?->name;
                }

                // Алба сонгоод нэгж хоосон бол албын нэгжийг авна (Салбар → Нэгж → Алба)
                if ($column === 'department' && $record?->unit_id && ! $model->unit_id && ($unit = Unit::find($record->unit_id))) {
                    $model->unit_id = $unit->id;
                    $model->unit = $unit->name;
                }
                // Нэгжгүй албыг нэгжтэй хамт сонговол алба тэр нэгжид харьяалагдана (дараагийн бүртгэлд шүүлт ажиллана)
                if ($column === 'department' && $record && ! $record->unit_id && $model->unit_id) {
                    $record->forceFill(['unit_id' => $model->unit_id])->saveQuietly();
                }
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
