<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Төхөөрөмж захиалгын нэг мөр (Процессор ×1): Админ мөр бүрийг тусад нь шийдвэрлэнэ */
class AssetRequestItem extends Model
{
    public const STATUSES = [
        'pending' => 'Хүлээгдэж буй',
        'approved' => 'Зөвшөөрсөн',
        'delivered' => 'Хүлээлгэн өгсөн',
        'rejected' => 'Татгалзсан',
    ];

    public const BADGES = [
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-blue-100 text-blue-700',
        'delivered' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
    ];

    protected $fillable = ['asset_request_id', 'name', 'quantity', 'status', 'note', 'handled_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function assetRequest(): BelongsTo
    {
        return $this->belongsTo(AssetRequest::class);
    }

    /** «UPS ×2» */
    public function label(): string
    {
        return $this->name.($this->quantity > 1 ? ' ×'.$this->quantity : '');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
