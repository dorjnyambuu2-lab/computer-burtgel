<?php

namespace App\Models;

/**
 * Алба хэлтэс: салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч department_id-аар холбогдоно;
 * тэдний «department» текст нь харуулах, шүүх, хайхад зориулсан хуулбар (нэр солигдоход синк).
 */
class Department extends BranchList
{
    public const COLUMN = 'department';
}
