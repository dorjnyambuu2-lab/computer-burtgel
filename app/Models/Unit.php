<?php

namespace App\Models;

/**
 * Нэгж (алба хэлтэстэй адил): салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч unit_id-аар холбогдоно;
 * тэдний «unit» текст нь харуулах, хайхад зориулсан хуулбар (нэр солигдоход синк).
 */
class Unit extends BranchList
{
    public const COLUMN = 'unit';
}
