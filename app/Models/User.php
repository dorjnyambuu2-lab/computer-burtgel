<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'superadmin' => 'Superadmin',
        'admin'      => 'Админ',
        'employee'   => 'Ажилтан',
    ];

    public const STATUSES = [
        'active'   => 'Идэвхтэй',
        'inactive' => 'Идэвхгүй',
    ];

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'branch_id', 'is_active', 'must_change_password'];

    protected static function booted(): void
    {
        // Нэр солигдвол хариуцаж буй төхөөрөмжүүдийн «Хариуцагч» (computers.assigned_to) шинэ нэрээр харагдана.
        // Шууд DB-ээр шинэчилнэ: хариуцагч өөрчлөгдөөгүй тул «хүлээн авсан» акт, түүх хэвээр.
        static::updated(function (User $user) {
            if ($user->wasChanged('name')) {
                \Illuminate\Support\Facades\DB::table('computers')->where('assigned_user_id', $user->id)
                    ->update(['assigned_to' => $user->name]);
            }
        });
    }

    /** Хариуцах / харьяалах салбар (Супер админд байхгүй — бүх салбар) */
    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Салбарын хязгаарлалттай эсэх (Админ) */
    public function isBranchScoped(): bool
    {
        return $this->role === 'admin';
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /** Энэ хэрэглэгчид хариуцуулсан компьютерууд */
    public function computers(): HasMany
    {
        return $this->hasMany(Computer::class, 'assigned_user_id');
    }

    /**
     * Текстээр (username / имэйл / нэр) хэрэглэгч хайна.
     * Зөвхөн ЯГ НЭГ хэрэглэгч таарвал буцаана, давхцвал null.
     */
    public static function findByIdentifier(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $byLogin = static::query()
            ->where('username', $value)
            ->orWhere('email', $value)
            ->get();

        if ($byLogin->count() === 1) {
            return $byLogin->first();
        }

        $byName = static::query()->where('name', $value)->get();

        return $byName->count() === 1 ? $byName->first() : null;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Төхөөрөмжийн хариуцагч болгож болох хэрэглэгчид: идэвхтэй. Админ сонгож байгаа бол
     * Супер админыг оруулахгүй, зөвхөн өөрийн салбарын хэрэглэгчид (Супер админ өөрөө бол бүгд).
     */
    public function scopeAssignableBy(\Illuminate\Database\Eloquent\Builder $query, ?User $actor): void
    {
        $query->where('is_active', true)
            ->when(! $actor?->isSuperadmin(), fn ($q) => $q->where('role', '!=', 'superadmin'))
            ->when($actor?->isBranchScoped(), fn ($q) => $q->where('branch_id', $actor->branch_id ?? 0));
    }

    /** Дээрхтэй ижил шалгалт (validation rule) */
    public static function assignableRule(?User $actor): \Illuminate\Validation\Rules\Exists
    {
        $rule = \Illuminate\Validation\Rule::exists('users', 'id')->where('is_active', true);

        if ($actor?->isSuperadmin()) {
            return $rule;
        }

        $rule->whereNot('role', 'superadmin');

        return $actor?->isBranchScoped() ? $rule->where('branch_id', $actor->branch_id ?? 0) : $rule;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Компьютер нэмэх/засах/устгах эрхтэй юу
    public function canManageComputers(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    /**
     * Хэрэглэгчийн хуудас:
     *  - Супер админ: админ (салбарын хариуцагч) бүртгэнэ, бүх хэрэглэгчийг харж засна
     *  - Админ: зөвхөн өөрийн салбарын ажилтныг бүртгэж, засна
     */
    public function canManageUsers(): bool
    {
        return $this->isSuperadmin() || ($this->isAdmin() && $this->branch_id !== null);
    }

    // Өөр хэрэглэгчийг засах эрхтэй юу
    public function canEditUser(User $target): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        // Админ: өөрийн салбарын ажилтан
        return $this->canManageUsers() && $target->role === 'employee' && $target->branch_id === $this->branch_id;
    }

    // Устгах эрхтэй юу (өөрийгөө устгах боломжгүй)
    public function canDeleteUser(User $target): bool
    {
        return $this->id !== $target->id && $this->canEditUser($target);
    }

    /**
     * Олгож болох эрхүүд. Супер админ — Супер админ, Админ (ажилтныг салбарын админ бүртгэнэ;
     * одоо байгаа ажилтныг засахад эрх нь хэвээр үлдэж болно). Админ — зөвхөн Ажилтан.
     */
    public function assignableRoles(?User $target = null): array
    {
        if (! $this->isSuperadmin()) {
            return ['employee' => self::ROLES['employee']];
        }

        $roles = array_diff_key(self::ROLES, ['employee' => 1]);

        return $target?->role === 'employee' ? $roles + ['employee' => self::ROLES['employee']] : $roles;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public static function activeSuperadmins()
    {
        return static::query()->where('role', 'superadmin')->where('is_active', true)->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public static function activeStaff()
    {
        return static::query()->whereIn('role', ['superadmin', 'admin'])->where('is_active', true)->get();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'superadmin' => 'Superadmin',
            'admin' => 'Админ',
            default => 'Ажилтан',
        };
    }

    public function roleBadgeClass(): string
    {
        return match ($this->role) {
            'superadmin' => 'bg-amber-100 text-amber-700',
            'admin' => 'bg-indigo-100 text-indigo-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}