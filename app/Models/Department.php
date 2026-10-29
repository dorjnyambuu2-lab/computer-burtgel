<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Алба хэлтэс: салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч department_id-аар холбогдоно;
 * тэдний «department» текст нь харуулах, шүүх, хайхад зориулсан хуулбар (нэр солигдоход синк).
 */
class Department extends Model
{
    use Concerns\LogsActivity; // нэмэх, нэр солих, нэгтгэх, устгах «Үйлдлийн түүх»-д

    protected $fillable = ['branch_id', 'name'];

    protected static function booted(): void
    {
        // Нэр солигдвол холбогдсон төхөөрөмж, хэрэглэгчийн текст шинэ нэрээр (түүхэнд «Зассан» бичигдэхгүй)
        static::updated(function (Department $department) {
            if ($department->wasChanged('name')) {
                foreach (['computers', 'users'] as $table) {
                    DB::table($table)->where('department_id', $department->id)->update(['department' => $department->name]);
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function computers(): HasMany
    {
        return $this->hasMany(Computer::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Нэрийн цагаан зай, давхар зайг цэвэрлэнэ; хоосон бол null */
    public static function cleanName(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        return $name === '' ? null : $name;
    }

    /**
     * Салбарын (том/жижиг үсэг ялгахгүй) хэлтэс; байхгүй бол үүсгэнэ. Нэр хоосон бол null.
     * Төхөөрөмж, хэрэглэгч хадгалахад «department» текстээс department_id-г тааруулахад ашиглана.
     */
    public static function resolve(?int $branchId, ?string $name): ?self
    {
        if (($name = self::cleanName($name)) === null) {
            return null;
        }

        // Харьцуулалт PHP-д (SQLite-ийн LOWER кирилл үсгийг жижигрүүлдэггүй); салбарын хэлтэс цөөн
        $key = mb_strtolower($name);

        return static::query()->where('branch_id', $branchId)->get()->first(fn ($d) => mb_strtolower($d->name) === $key)
            ?? static::create(['branch_id' => $branchId, 'name' => $name]);
    }
}
