@php
    $u = auth()->user();
    // Хүлээгдэж буй хүсэлтийн тоо (Админд зөвхөн өөрийн салбарынх — visibleTo)
    $count = fn (string $table, string $model) => \Illuminate\Support\Facades\Schema::hasTable($table)
        ? $model::where('status', 'pending')->when(method_exists($model, 'scopeVisibleTo'), fn ($q) => $q->visibleTo($u))->count() : 0;

    // Цэс бүлгээр: [гарчиг => [[href, нэр, icon, идэвхтэй эсэх, тоо], ...]]
    // (QR уншуулах, Миний профайл нь дээд мөрөнд байгаа тул админы цэсэнд давтахгүй)
    $home = ['/', 'Хянах самбар', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10', fn() => request()->is('/')];

    if ($u && $u->canManageComputers()) {
        $navGroups = [
            '' => [$home],
            'Төхөөрөмж' => [
                ['/computers', 'Бүх төхөөрөмж', 'M4 5h16v11H4zM8 20h8M12 16v4',
                    fn() => request()->is('computers') || (request()->is('computers/*') && ! request()->is('computers/trash', 'computers/scan', 'computers/bulk-assign', 'computers/bulk-create', 'computers/create'))],
                ['/computers/create', 'Шинээр бүртгэх', 'M12 5v14M5 12h14', fn() => request()->is('computers/create')],
                ['/computers/bulk-create', 'Багцаар бүртгэх', 'M4 4h16v16H4zM12 8v8M8 12h8', fn() => request()->is('computers/bulk-create')],
                ['/computers/bulk-assign', 'Төхөөрөмж олгох', 'M15 7a4 4 0 11-8 0 4 4 0 018 0zM3 21a8 8 0 0114 0M19 8v6M16 11h6', fn() => request()->is('computers/bulk-assign')],
                // Төхөөрөмж шилжүүлэх: зөвхөн салбарын Админ (тоо: өмнөх шийдвэрлэгдээгүй хүсэлт)
                ...($u->isAdmin() ? [['/transfers', 'Төхөөрөмж шилжүүлэх', 'M7 7h11l-3-3M17 17H6l3 3',
                    fn() => request()->is('transfers*'), \App\Models\ComputerTransfer::query()->open()->visibleTo($u)->count()]] : []),
                ['/repairs', 'Засвар', 'M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
                    fn() => request()->is('repairs*'), \App\Models\Computer::where('status', \App\Enums\ComputerStatus::InRepair)->count()],
            ],
            'Хүсэлт' => array_filter([
                ['/requests', 'Ажилтны хүсэлт', 'M9 5h10M9 12h10M9 19h10M4 5h.01M4 12h.01M4 19h.01',
                    fn() => request()->is('requests*'), $count('asset_requests', \App\Models\AssetRequest::class)],
                $u->isSuperadmin()
                    ? ['/password-requests', 'Нууц үг сэргээх', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4',
                        fn() => request()->is('password-requests*'), $count('password_reset_requests', \App\Models\PasswordResetRequest::class)]
                    : null,
            ]),
            'Систем' => array_filter([
                $u->canManageUsers()
                    ? ['/users', $u->isSuperadmin() ? 'Хэрэглэгч' : 'Ажилтан', 'M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z', fn() => request()->is('users*')]
                    : null,
                $u->isSuperadmin()
                    ? ['/branches', 'Салбар', 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01', fn() => request()->is('branches*')]
                    : null,
                ['/activity', 'Үйлдлийн түүх', 'M12 8v5l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z', fn() => request()->is('activity*')],
                ['/computers/trash', 'Устгасан', 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13', fn() => request()->is('computers/trash')],
            ]),
        ];
    } else {
        // Ажилтан: хяналтын самбар + өөрийн төхөөрөмжүүд + профайл
        $navGroups = ['' => [
            $home,
            ['/my/computers', 'Миний төхөөрөмж', 'M4 5h16v11H4zM8 20h8M12 16v4', fn() => request()->is('my/computers*')],
            ['/profile', 'Миний профайл', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0', fn() => request()->is('profile*')],
        ]];
    }

    $logout = \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : url('/logout');

    $unreadNotifications = $u && \Illuminate\Support\Facades\Schema::hasTable('notifications')
        ? $u->unreadNotifications()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Хянах самбар') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
    /* Хуучин хуудсуудын (computers, users, activity, trash) элементүүдийг шинэ загварт тааруулах */
    @layer components {
        main h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; }
        main .card, main form.filter-bar { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; box-shadow:0 1px 2px rgb(15 23 42/.04); }
        main .filter-bar { display:flex; flex-wrap:wrap; gap:.75rem; padding:1rem; margin-bottom:1rem; }
        main .filter-bar input, main .filter-bar select { flex:1 1 10rem; }
        main table { width:100%; border-collapse:collapse; font-size:.875rem; background:#fff; }
        main thead th { background:#f8fafc; color:#64748b; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; text-align:left; padding:.75rem 1rem; white-space:nowrap; }
        main tbody td { padding:.75rem 1rem; border-top:1px solid #f1f5f9; vertical-align:middle; }
        main tbody tr:hover { background:#f8fafc; }
        main input:not([type=checkbox]):not([type=radio]), main select, main textarea { border:1px solid #cbd5e1; border-radius:.75rem; padding:.5rem .75rem; background:#fff; font-size:.875rem; }
        main input:focus, main select:focus, main textarea:focus { border-color:#3b82f6; outline:2px solid #dbeafe; }
        main button, main a.btn, main .btn { border-radius:.75rem; padding:.5rem 1rem; font-size:.875rem; font-weight:600; border:1px solid #cbd5e1; background:#fff; }
        main .btn-primary, main button[type=submit]:not(.btn-danger):not(.btn-link) { background:#2563eb; border-color:#2563eb; color:#fff; }
        main .btn-primary:hover, main button[type=submit]:hover { background:#1d4ed8; }
        main a:not(.btn):not([class*=bg-]) { color:#2563eb; }
        main nav[role=navigation] { margin-top:1rem; }
    }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased" x-data>
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <input type="checkbox" id="menu-toggle" class="peer hidden">
    <label for="menu-toggle" class="fixed inset-0 z-30 hidden bg-slate-900/40 peer-checked:block lg:!hidden"></label>
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white transition-transform peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex items-center gap-3 px-5 py-5">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
            </div>
            <div>
                <div class="font-bold leading-tight">{{ config('app.name') }}</div>
                <div class="text-xs text-slate-500">Тоног төхөөрөмжийн бүртгэл</div>
            </div>
        </div>
        <nav class="overflow-y-auto px-3 pb-6" style="max-height: calc(100vh - 5rem)">
            @foreach ($navGroups as $group => $items)
                @if ($group !== '')
                    <div class="px-3 pb-1.5 pt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</div>
                @endif
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        @php [$href, $label, $icon, $active] = $item; $badge = $item[4] ?? 0; @endphp
                        <a href="{{ url($href) }}"
                           class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ $active() ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' }}">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                            {{ $label }}
                            @if ($badge > 0)<span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $badge }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-8">
            <label for="menu-toggle" class="cursor-pointer rounded-lg border border-slate-200 p-2 lg:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>
            <div class="hidden text-sm text-slate-500 sm:block">Систем / <span class="font-semibold text-slate-900">@yield('title', 'Хянах самбар')</span></div>

            <div class="ml-auto flex items-center gap-3">
                {{-- QR уншуулах: зөвхөн Superadmin / Админ-д (утсан дээр хурдан нээхэд зориулсан) --}}
                @if ($u && $u->canManageComputers())
                    <a href="{{ url('/computers/scan') }}" title="QR уншуулах"
                       class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/></svg>
                        <span class="hidden sm:inline">QR уншуулах</span>
                    </a>
                @endif

                <a href="{{ route('notifications.index') }}" title="Мэдэгдэл"
                   class="relative rounded-lg border border-slate-200 p-2 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @if ($unreadNotifications > 0)
                        <span class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-semibold leading-5 text-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-1.5 transition hover:border-blue-300 hover:bg-blue-50" title="Миний профайл">
                    <div class="grid h-9 w-9 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                        {{ mb_strtoupper(mb_substr($u->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="hidden leading-tight sm:block">
                        <div class="text-sm font-semibold">{{ $u->name ?? '' }}</div>
                        <div class="text-xs text-slate-500">{{ $u?->roleLabel() }}@if ($u?->isBranchScoped()) · {{ $u->branch?->name ?? 'Салбаргүй' }}@endif</div>
                    </div>
                </a>
                <form method="POST" action="{{ $logout }}">@csrf
                    <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-100">Гарах</button>
                </form>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
            @endif
            @if ($u?->isBranchScoped() && ! $u->branch_id)
                <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Танд хариуцах салбар оноогоогүй байна, тиймээс төхөөрөмж харагдахгүй. Супер админд хандаж салбараа оноолгоно уу.
                </div>
            @endif
            @if (session('warning'))
                <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
            @endif
            @yield('content')
            {{ $slot ?? '' }}
        </main>
    </div>
</div>
</body>
</html>