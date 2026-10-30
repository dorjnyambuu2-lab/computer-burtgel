<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Салбарын жагсаалт (Алба, Нэгж): салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч {COLUMN}_id-аар холбогдоно;
 * тэдний {COLUMN} текст нь харуулах, шүүх, хайхад зориулсан хуулбар (нэр солигдоход синк).
 * Дэд класс нь COLUMN тогтмолоор текст баганаа заана ('department', 'unit').
 */
abstract class BranchList extends Model
{
    use Concerns\LogsActivity; // нэмэх, нэр солих, нэгтгэх, устгах «Үйлдлийн түүх»-д

    /** computers, users хүснэгтийн текст багана */
    public const COLUMN = '';

    protected $fillable = ['branch_id', 'name'];

    protected static function booted(): void
    {
        // Нэр солигдвол холбогдсон төхөөрөмж, хэрэглэгчийн текст шинэ нэрээр (түүхэнд «Зассан» бичигдэхгүй)
        static::updated(function (BranchList $list) {
            if ($list->wasChanged('name')) {
                foreach (['computers', 'users'] as $table) {
                    DB::table($table)->where(static::COLUMN.'_id', $list->id)->update([static::COLUMN => $list->name]);
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
        return $this->hasMany(Computer::class, static::COLUMN.'_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, static::COLUMN.'_id');
    }

    /** Нэрийн цагаан зай, давхар зайг цэвэрлэнэ; хоосон бол null */
    public static function cleanName(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        return $name === '' ? null : $name;
    }

    /**
     * Салбарын (том/жижиг үсэг ялгахгүй) бичлэг; байхгүй бол үүсгэнэ. Нэр хоосон бол null.
     * Төхөөрөмж, хэрэглэгч хадгалахад текстээс {COLUMN}_id-г тааруулахад ашиглана.
     */
    public static function resolve(?int $branchId, ?string $name): ?static
    {
        if (($name = self::cleanName($name)) === null) {
            return null;
        }

        // Харьцуулалт PHP-д (SQLite-ийн LOWER кирилл үсгийг жижигрүүлдэггүй); салбарын жагсаалт цөөн
        $key = mb_strtolower($name);

        return static::query()->where('branch_id', $branchId)->get()->first(fn ($d) => mb_strtolower($d->name) === $key)
            ?? static::create(['branch_id' => $branchId, 'name' => $name]);
    }
}
