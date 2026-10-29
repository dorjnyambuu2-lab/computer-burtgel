<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Салбар: салбар бүрийн Админ зөвхөн тухайн салбарын төхөөрөмж, ажилтныг удирдана */
class Branch extends Model
{
    use Concerns\LogsActivity; // нэмэх, засах, устгах «Үйлдлийн түүх»-д

    protected $fillable = ['name', 'code', 'address'];

    public function computers(): HasMany
    {
        // Салбарын тоог Супер админ хардаг тул салбарын шүүлтгүй
        return $this->hasMany(Computer::class)->withoutGlobalScope(Scopes\BranchScope::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Салбарын админууд */
    public function admins(): HasMany
    {
        return $this->users()->where('role', 'admin');
    }

    /**
     * Хэрэглэгчийн формд урьдчилан сонгох салбар: Админд — админгүй (хамгийн цөөн админтай) салбар,
     * Ажилтанд — хамгийн цөөн ажилтантай салбар. Тэнцүү бол нэрээр. $except: засаж буй хэрэглэгчийг тооцохгүй.
     */
    public static function autoFor(string $role, ?User $except = null): ?self
    {
        return static::query()
            ->withCount(['users as same_role_count' => fn ($q) => $q->where('role', $role)
                ->when($except?->exists, fn ($q) => $q->whereKeyNot($except->id))])
            ->orderBy('same_role_count')->orderBy('name')->orderBy('id')
            ->first();
    }

    /** Сонгох жагсаалтад: «Төв салбар (TOV)» */
    /** Салбарын ID код — бүртгэлийн дугаарын «B» хэсэгтэй ижил (жишээ: B1) */
    public function idCode(): string
    {
        return 'B'.$this->id;
    }

    public function label(): string
    {
        return $this->name.($this->code ? ' ('.$this->code.')' : '');
    }
}
