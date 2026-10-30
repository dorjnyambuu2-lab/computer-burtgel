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

    /** Захиалгын мөрүүд (Процессор ×1, UPS ×2); эвдрэлд хоосон */
    public function items()
    {
        return $this->hasMany(AssetRequestItem::class)->orderBy('id');
    }

    /** «Процессор, Printer, UPS ×2» (гарчиг, мэдэгдэлд) */
    public static function itemsSubject(iterable $items): string
    {
        return mb_substr(collect($items)->map(fn ($i) => $i['name'].((int) ($i['quantity'] ?? 1) > 1 ? ' ×'.(int) $i['quantity'] : ''))->implode(', '), 0, 150);
    }

    /**
     * Захиалгын ерөнхий төлвийг мөрүүдээс: бүгд хүлээгдэж буй → pending; бүгд татгалзсан → rejected;
     * хүлээгдэж буй, зөвшөөрөөд өгөөгүй мөргүй → done; бусад (хэсэгчлэн шийдвэрлэсэн) → in_progress.
     */
    public function syncStatusFromItems(): void
    {
        $statuses = $this->items()->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }

        $this->status = match (true) {
            $statuses->every(fn ($s) => $s === 'pending') => 'pending',
            $statuses->every(fn ($s) => $s === 'rejected') => 'rejected',
            $statuses->every(fn ($s) => in_array($s, ['delivered', 'rejected'], true)) => 'done',
            default => 'in_progress',
        };
        $this->save();
    }

    /** Төлвийн нэр: захиалгад «Зөвшөөрсөн», «Хүлээлгэн өгсөн», эвдрэлд «Хийгдэж буй», «Дууссан» */
    public function statusLabel(): string
    {
        // Зарим мөр шийдвэрлэгдээгүй хэвээр бол «Хэсэгчлэн шийдвэрлэсэн»
        if ($this->type === 'request' && $this->status === 'in_progress' && $this->relationLoaded('items')
            && $this->items->contains('status', 'pending')) {
            return 'Хэсэгчлэн шийдвэрлэсэн';
        }

        return \App\Http\Controllers\AssetRequestController::statusesFor($this->type)[$this->status] ?? (string) $this->status;
    }

    /** Хүсэлтийг шийдвэрлэсэн админ */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}