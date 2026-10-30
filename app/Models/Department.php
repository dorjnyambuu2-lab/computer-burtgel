<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Алба хэлтэс: салбар бүрт нэр нь давтагдахгүй, нэгжид харьяалагдаж болно (Салбар → Нэгж → Алба, заавал биш).
 * Төхөөрөмж, хэрэглэгч department_id-аар холбогдоно; тэдний «department» текст нь харуулах, шүүх, хайхад
 * зориулсан хуулбар (нэр солигдоход синк).
 */
class Department extends BranchList
{
    public const COLUMN = 'department';

    protected $fillable = ['branch_id', 'unit_id', 'name'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
