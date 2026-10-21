<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Models\Computer;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Хуучин маршрутууд [DashboardController::class, 'index'] гэж дуудахад зориулсан. */
    public function __invoke(): View
    {
        return $this->index();
    }

    public function index(): View
    {
        $byStatus = Computer::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byType = Computer::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return view('dashboard', [
            'total' => Computer::count(),
            'statuses' => collect(ComputerStatus::cases())->map(fn ($s) => [
                'enum' => $s,
                'count' => (int) ($byStatus[$s->value] ?? 0),
            ]),
            'types' => collect(ComputerType::cases())->map(fn ($t) => [
                'enum' => $t,
                'count' => (int) ($byType[$t->value] ?? 0),
            ])->filter(fn ($t) => $t['count'] > 0),
            'departments' => Computer::query()
                ->whereNotNull('department')
                ->selectRaw('department, count(*) as total')
                ->groupBy('department')->orderByDesc('total')->limit(8)->get(),
            'expiring' => Computer::warrantyExpiringWithin(60)->orderBy('warranty_until')->limit(8)->get(),
            'expiredCount' => Computer::warrantyExpired()->count(),
            'recent' => Computer::latest()->limit(6)->get(),
        ]);
    }
}
