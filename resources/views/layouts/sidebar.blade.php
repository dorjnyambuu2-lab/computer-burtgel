@php
    $u = auth()->user();
    $path = '/' . ltrim(request()->path(), '/');
    $nav = [
        ['/', 'Хянах самбар', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10', fn() => request()->is('/')],
        ['/computers', 'Компьютер', 'M4 5h16v11H4zM8 20h8M12 16v4', fn() => request()->is('computers') || (request()->is('computers/*') && ! request()->is('computers/trash'))],
        ['/computers/trash', 'Устгасан', 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13', fn() => request()->is('computers/trash')],
        ['/activity', 'Үйлдлийн түүх', 'M12 8v5l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z', fn() => request()->is('activity*')],
    ];
    if ($u && ($u->isSuperadmin() || $u->isAdmin())) {
        $nav[] = ['/users', 'Хэрэглэгч', 'M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z', fn() => request()->is('users*')];
    }
    if ($u && $u->canManageComputers()) {
        $pending = \Illuminate\Support\Facades\Schema::hasTable('asset_requests')
            ? \App\Models\AssetRequest::where('status', 'pending')->count() : 0;
        $nav[] = ['/requests', 'Хүсэлт', 'M9 5h10M9 12h10M9 19h10M4 5h.01M4 12h.01M4 19h.01', fn() => request()->is('requests*'), $pending];

        $pendingTransfers = \Illuminate\Support\Facades\Schema::hasTable('computer_transfers')
            ? \App\Models\ComputerTransfer::where('status', 'pending')->count() : 0;
        $nav[] = ['/transfers', 'Шилжүүлэх хүсэлт', 'M7 7h11l-3-3M17 17H6l3 3', fn() => request()->is('transfers*'), $pendingTransfers];
    }
    // Ажилтан: хяналтын самбар + өөрийн төхөөрөмжүүд
    if ($u && ! $u->canManageComputers()) {
        $nav = [
            $nav[0],
            ['/my/computers', 'Миний төхөөрөмж', 'M4 5h16v11H4zM8 20h8M12 16v4', fn() => request()->is('my/computers*')],
        ];
    }
    // Бүх эрх: профайл
    $nav[] = ['/profile', 'Миний профайл', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0', fn() => request()->is('profile*')];

    $logout = \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : url('/logout');
@endphp
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Хянах самбар') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
        <div class="px-5 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Үндсэн цэс</div>
        <nav class="space-y-1 px-3">
            @foreach ($nav as $item)
                @php [$href, $label, $icon, $active] = $item; $badge = $item[4] ?? 0; @endphp
                <a href="{{ url($href) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $active() ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    {{ $label }}
                    @if ($badge > 0)<span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $badge }}</span>@endif
                </a>
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
            @if (request()->is('/') && $u && $u->canManageComputers())
            <form action="{{ $u && ! $u->canManageComputers() ? url('/') : url('/computers') }}" method="GET" class="ml-auto hidden w-80 md:block">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Сериал, нэр, хариуцагчаар хайх…"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100">
            </form>
            @endif
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-1.5 transition hover:border-blue-300 hover:bg-blue-50" title="Миний профайл">
                    <div class="grid h-9 w-9 place-items-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                        {{ mb_strtoupper(mb_substr($u->name ?? 'U', 0, 2)) }}
                    </div>
                    <div class="hidden leading-tight sm:block">
                        <div class="text-sm font-semibold">{{ $u->name ?? '' }}</div>
                        <div class="text-xs text-slate-500">{{ $u?->roleLabel() }}</div>
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
            @yield('content')
            {{ $slot ?? '' }}
        </main>
    </div>
</div>
</body>
</html>