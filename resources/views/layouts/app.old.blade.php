<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Нүүр') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
@auth
    @php $me = auth()->user(); @endphp
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-bold text-indigo-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ config('app.name') }}
                </a>
                <nav class="flex gap-1 text-sm font-medium">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Хяналтын самбар</a>
                    <a href="{{ route('computers.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('computers.*') && ! request()->routeIs('computers.trash') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Компьютерууд</a>

                    {{-- Superadmin + Админ --}}
                    @if ($me->canManageComputers())
                        <a href="{{ route('activity.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('activity.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Үйлдлийн түүх</a>
                        <a href="{{ route('computers.trash') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('computers.trash') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Устгасан</a>
                    @endif

                    {{-- Зөвхөн superadmin --}}
                    @if ($me->isSuperadmin())
                        <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' }}">Хэрэглэгчид</a>
                    @endif
                </nav>
            </div>

            <div class="flex items-center gap-3 text-sm">
                @if ($me->canManageComputers())
                    <a href="{{ route('computers.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-700">+ Компьютер нэмэх</a>
                @endif

                <div class="hidden items-center gap-2 sm:flex">
                    <span class="text-slate-500">{{ $me->name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $me->roleBadgeClass() }}">
                        {{ $me->roleLabel() }}
                    </span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-600 hover:bg-slate-50">Гарах</button>
                </form>
            </div>
        </div>
    </header>
@endauth

<main class="mx-auto max-w-7xl px-4 py-6">
    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- UserController::destroy-оос ирэх алдаа --}}
    @if ($errors->has('user'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('user') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>
