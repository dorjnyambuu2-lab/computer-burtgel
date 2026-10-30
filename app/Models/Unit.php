<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Нэгж (алба хэлтэстэй адил): салбар бүрт нэр нь давтагдахгүй, дотроо албуудтай (Салбар → Нэгж → Алба).
 * Төхөөрөмж, хэрэглэгч unit_id-аар холбогдоно; тэдний «unit» текст нь харуулах, хайхад зориулсан хуулбар.
 */
class Unit extends BranchList
{
    public const COLUMN = 'unit';

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
