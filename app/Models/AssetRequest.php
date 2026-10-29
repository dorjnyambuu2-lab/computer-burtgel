<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetRequest extends Model
{
    protected $fillable = [
        'user_id', 'computer_id', 'type', 'subject', 'description',
        'status', 'admin_note', 'handled_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function computer()
    {
        return $this->belongsTo(Computer::class);
    }

    /**
     * Харах эрхтэй хүсэлтүүд: Админ бол өөрийн салбарын төхөөрөмжийн хүсэлт, эсвэл төхөөрөмжгүй бол
     * өөрийн салбарын ажилтны хүсэлт (жишээ нь шинэ төхөөрөмж хүссэн).
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if (! $user->isBranchScoped()) {
            return;
        }

        $query->where(fn ($q) => $q
            ->whereHas('computer') // Computer-ийн BranchScope салбараар шүүнэ
            ->orWhere(fn ($q) => $q->whereNull('computer_id')->whereHas('user', fn ($u) => $u->where('branch_id', $user->branch_id ?? 0))));
    }

    /** Төлвийн нэр: захиалгад «Зөвшөөрсөн», «Хүлээлгэн өгсөн», эвдрэлд «Хийгдэж буй», «Дууссан» */
    public function statusLabel(): string
    {
        return \App\Http\Controllers\AssetRequestController::statusesFor($this->type)[$this->status] ?? (string) $this->status;
    }

    /** Хүсэлтийг шийдвэрлэсэн админ */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}