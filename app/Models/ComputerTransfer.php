<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Шилжүүлэг заавал IT-аар дамжина (ажилтан шууд өөр ажилтанд өгөхгүй):
 *   pending   — ажилтан IT-д хүлээлгэн өгөх хүсэлт илгээсэн (төхөөрөмж ажилтанд хэвээр)
 *   received  — IT хүлээн авсан: хариуцагчгүй, «Агуулахад» төлөвт
 *   completed — IT дараагийн ажилтанд олгосон
 *   rejected  — татгалзсан
 *   transferred — Админ / Супер админ шууд шилжүүлсэн («Төхөөрөмж шилжүүлэх»); destination нь
 *                 user (ажилтан, админ), department (алба хэлтэс), storage (агуулах)
 *   approved  — хуучин (шууд шилжүүлдэг байсан үеийн) бичлэг
 */
class ComputerTransfer extends Model
{
    public const STATUSES = [
        'pending' => ['IT хүлээн авахыг хүлээж буй', 'bg-amber-100 text-amber-800'],
        'received' => ['IT-д хүлээн авсан · олгохыг хүлээж буй', 'bg-sky-100 text-sky-800'],
        'completed' => ['Дараагийн ажилтанд олгосон', 'bg-emerald-100 text-emerald-700'],
        'transferred' => ['Шилжүүлсэн', 'bg-emerald-100 text-emerald-700'],
        'rejected' => ['Татгалзсан', 'bg-rose-100 text-rose-700'],
        'approved' => ['Батлагдсан (шууд)', 'bg-slate-100 text-slate-600'],
    ];

    protected $fillable = [
        'computer_id', 'from_user_id', 'from_label', 'to_user_id', 'destination', 'to_department', 'reason', 'status', 'decided_by', 'decided_at',
        'received_by', 'received_at', 'issued_to_user_id', 'issued_by', 'completed_at',
    ];

    protected $casts = ['decided_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime'];

    public function computer() { return $this->belongsTo(Computer::class)->withTrashed(); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    /** Ажилтны санал болгосон дараагийн хариуцагч (заавал биш) */
    public function toUser()   { return $this->belongsTo(User::class, 'to_user_id'); }
    public function issuedTo() { return $this->belongsTo(User::class, 'issued_to_user_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    /** Хэнээс: өмнөх хариуцагч, эсвэл хариуцагчгүй байсан бол өмнөх байдал («Агуулах», «Алба: IT») */
    public function fromText(): string
    {
        return $this->fromUser?->name ?? $this->from_label ?? '—';
    }

    /** Хэнд/хаашаа шилжсэн: хэрэглэгч, алба хэлтэс эсвэл агуулах */
    public function destinationText(): ?string
    {
        return match ($this->destination) {
            'department' => 'Алба: '.$this->to_department,
            'storage' => 'Агуулах',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-slate-100 text-slate-600';
    }

    /** Нээлттэй (дуусаагүй) шилжүүлэг: хүсэлт эсвэл IT-д байгаа */
    public function scopeOpen(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn('status', ['pending', 'received']);
    }

    /** Админ: зөвхөн өөрийн салбарын төхөөрөмжийн шилжүүлэг (Computer-ийн BranchScope) */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if ($user->isBranchScoped()) {
            $query->whereHas('computer');
        }
    }
}
