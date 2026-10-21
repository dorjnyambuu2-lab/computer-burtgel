<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputerEvent extends Model
{
    public const TYPES = [
        'assigned' => 'Олгосон',
        'returned' => 'Буцаан авсан',
        'repair'   => 'Засварт бүртгэсэн',
        'repair_update' => 'Засварын явц',
        'repaired' => 'Засвар дууссан',
    ];

    protected $fillable = ['computer_id', 'actor_id', 'user_id', 'type', 'note', 'cost'];

    /** cost: засварын зардал (₮), засварын үйлдэлд л оруулна */
    protected $casts = ['cost' => 'decimal:2'];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    /** Үйлдлийг хийсэн IT ажилтан */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Холбогдсон ажилтан (хариуцагч) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function badgeClass(): string
    {
        return match ($this->type) {
            'assigned' => 'bg-emerald-50 text-emerald-700',
            'returned' => 'bg-slate-100 text-slate-600',
            'repair'   => 'bg-amber-50 text-amber-700',
            'repair_update' => 'bg-blue-50 text-blue-700',
            'repaired' => 'bg-emerald-50 text-emerald-700',
            default    => 'bg-slate-100 text-slate-600',
        };
    }
}