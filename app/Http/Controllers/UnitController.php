<?php

namespace App\Http\Controllers;

use App\Models\Unit;

/** Нэгжийн бүртгэл: алба хэлтэстэй адил (DepartmentController) */
class UnitController extends DepartmentController
{
    protected const MODEL = Unit::class;

    protected const WORDS = ['нэгж', 'нэгжид', 'нэгжээ', 'нэгжийн'];
}
