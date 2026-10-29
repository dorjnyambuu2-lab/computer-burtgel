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
    use HasFactory, Notifiable, Concerns\SyncsDepartment, Concerns\LogsActivity;

    /** Түүхэнд бичихгүй: нууц үг (тусдаа «Нууц үг сольсон» үйлдэл), идэвхтэй байдлын техникийн талбарууд */
    protected static function activityIgnored(): array
    {
        return [...static::$activityIgnore, 'last_login_at', 'last_seen_at', 'is_online', 'email_verified_at'];
    }

    /** Түүхэнд харагдах нэр: «Овог Нэр (нэвтрэх нэр)» */
    public function activityLabel(): string
    {
        $name = trim($this->surname.' '.$this->name) ?: 'Хэрэглэгч #'.$this->getKey();

        return $this->username ? $name.' ('.$this->username.')' : $name;
    }

    public const ROLES = [
        'superadmin' => 'Супер админ',
        'admin'      => 'Админ',
        'employee'   => 'Ажилтан',
    ];

    public const STATUSES = [
        'active'   => 'Идэвхтэй',
        'inactive' => 'Идэвхгүй',
    ];

    protected $fillable = ['name', 'surname', 'username', 'email', 'password', 'role', 'branch_id', 'unit', 'department', 'department_id', 'position', 'is_active', 'must_change_password'];

    /** Ажлын мэдээллийн талбарууд (профайл болон компьютерийн хариуцагчид ашиглана) */
    public const JOB_FIELDS = ['surname', 'unit', 'department', 'position'];

    protected static function booted(): void
    {
        // Нэр солигдвол хариуцаж буй төхөөрөмжүүдийн «Хариуцагч» (computers.assigned_to) шинэ нэрээр харагдана.
        // Шууд DB-ээр шинэчилнэ: хариуцагч өөрчлөгдөөгүй тул «хүлээн авсан» акт, түүх хэвээр.
        // Код: шинэ хэрэглэгчид, эсвэл эрхийн бүлэг солигдоход (ажилтан ↔ админ) тухайн бүлгийн дараагийн дугаар
        static::saving(function (User $user) {
            $prefix = self::codePrefix($user->role);
            if (blank($user->code) || ! str_starts_with($user->code, $prefix)) {
                $user->code = self::nextCode($prefix);
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('name')) {
                \Illuminate\Support\Facades\DB::table('computers')->where('assigned_user_id', $user->id)
                    ->update(['assigned_to' => $user->name]);
            }
        });

        // Нууц үг солигдсон: өөрөө сольсон бол «Нууц үг сольсон», бусад (Админ түр нууц үг олгосон) «Нууц үг шинэчилсэн».
        // Нууц үгийн утга түүхэнд бичигдэхгүй
        static::updated(function (User $user) {
            if ($user->wasChanged('password')) {
                $user->recordActivity(\Illuminate\Support\Facades\Auth::id() === $user->id ? 'password_changed' : 'password_reset');
            }
        });

        // Алба хэлтэс: «department» текстийг салбарын хэлтэстэй тааруулна
        static::registerDepartmentSync();
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
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_online' => 'boolean',
        ];
    }

    /**
     * Хэрэглэгчийн ID код — бүртгэлийн дугаарын хэсэгтэй ижил:
     * Superadmin → SA1, Админ (бүртгэсэн хүн) → A2, Ажилтан (хариуцагч) → U5 (бүлэг тус бүр дараалсан)
     */
    public function idCode(): string
    {
        return $this->code ?: self::codePrefix($this->role).$this->id;
    }

    /** Кодын үсэг: Superadmin → SA, Ажилтан → U, Админ → A */
    public static function codePrefix(?string $role): string
    {
        return match ($role) {
            'superadmin' => 'SA',
            'employee' => 'U',
            default => 'A',
        };
    }

    /** Бүлгийн дараагийн код: SA1, A3, U6 … (бүлэг тус бүр 1-ээс дараалан) */
    public static function nextCode(string $prefix): string
    {
        // «A%» нь «SA…»-г оруулахгүй ч «A» угтвартай өөр код байж болзошгүй тул яг угтвар + тоог шүүнэ
        $max = static::query()->where('code', 'like', $prefix.'%')->pluck('code')
            ->filter(fn ($code) => preg_match('/^'.$prefix.'\d+$/', $code))
            ->map(fn ($code) => (int) substr($code, strlen($prefix)))
            ->max();

        return $prefix.((int) $max + 1);
    }

    /** Бүртгэлийн дугаарт орох тоо: хэрэглэгчийн кодын тоон хэсэг (A3 → 3, SA1 → 1), хэрэглэгчгүй бол 0 */
    public static function codeNumber(?int $userId): int
    {
        $code = $userId ? static::whereKey($userId)->value('code') : null;

        return $code ? (int) preg_replace('/^\D+/', '', $code) : 0;
    }

    /** Бүртгэлийн дугаарын бүртгэсэн хүний хэсэг: SA1 (Superadmin), A3 (Админ); хэрэглэгчгүй бол A0 */
    public static function registrarCode(?int $userId): string
    {
        $code = $userId ? static::whereKey($userId)->value('code') : null;

        return $code && preg_match('/^(SA|A)\d+$/', $code) ? $code : 'A'.static::codeNumber($userId);
    }

    /** ID-ийн нэр: «Superadmin ID», «Админ ID», «Ажилтны ID» */
    public function idLabel(): string
    {
        return match ($this->role) {
            'superadmin' => 'Супер админы ID',
            'admin' => 'Админ ID',
            default => 'Ажилтны ID',
        };
    }

    /** Сүүлийн хэдэн минутад үйлдэл хийсэн бол онлайн гэж тооцно */
    public const ONLINE_MINUTES = 5;

    /** Одоо системд байгаа эсэх: гараагүй, сүүлийн 5 минутад хуудас нээсэн */
    public function isOnline(): bool
    {
        return $this->is_online && $this->last_seen_at?->gte(now()->subMinutes(self::ONLINE_MINUTES));
    }

    public function scopeOnline(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_online', true)->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES));
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
     * Төхөөрөмжийн хариуцагч болгож болох хэрэглэгчид: идэвхтэй, Супер админ биш.
     * Админ сонгож байгаа бол зөвхөн өөрийн салбарын хэрэглэгчид (Супер админ сонгож байгаа бол бүх салбар).
     */
    public function scopeAssignableBy(\Illuminate\Database\Eloquent\Builder $query, ?User $actor): void
    {
        $query->where('is_active', true)
            ->where('role', '!=', 'superadmin')
            ->when($actor?->isBranchScoped(), fn ($q) => $q->where('branch_id', $actor->branch_id ?? 0));
    }

    /** Дээрхтэй ижил шалгалт (validation rule) */
    public static function assignableRule(?User $actor): \Illuminate\Validation\Rules\Exists
    {
        $rule = \Illuminate\Validation\Rule::exists('users', 'id')->where('is_active', true)->whereNot('role', 'superadmin');

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

    // Өөр хэрэглэгчийг засах эрхтэй юу (өөрийгөө энд засахгүй — «Профайл»-аас)
    public function canEditUser(User $target): bool
    {
        if ($this->id === $target->id) {
            return false;
        }

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
            'superadmin' => 'Супер админ',
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