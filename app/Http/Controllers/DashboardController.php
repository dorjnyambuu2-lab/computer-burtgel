<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Хянах самбар: тоо, график, жагсаалтыг dashboard.blade.php өөрөө тооцно (ажилтанд my.employee) */
    public function index(): View
    {
        return view('dashboard');
    }
}
