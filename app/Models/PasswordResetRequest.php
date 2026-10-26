<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetRequest extends Model
{
    public const STATUSES = [
        'pending' => 'Хүлээгдэж буй',
        'resolved' => 'Шинэчилсэн',
        'rejected' => 'Татгалзсан',
    ];

    protected $fillable = ['user_id', 'status', 'ip_address', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Хүсэлтийг харах, шийдвэрлэх эрх:
     *   Супер админ — админ, ажилтны бүх хүсэлт
     *   Админ — зөвхөн өөрийн салбарын ажилтны хүсэлт
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if ($user->isSuperadmin()) {
            return;
        }

        if ($user->isAdmin() && $user->branch_id !== null) {
            $query->whereHas('user', fn ($q) => $q->where('role', 'employee')->where('branch_id', $user->branch_id));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
