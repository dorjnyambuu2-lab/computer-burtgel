<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @include('partials.theme')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes guest-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        .guest-rise { animation: guest-rise .5s ease-out both; }
        .guest-grid {
            background-image: linear-gradient(rgb(15 23 42 / .045) 1px, transparent 1px),
                              linear-gradient(90deg, rgb(15 23 42 / .045) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at 50% 30%, #000 25%, transparent 70%);
        }
        @media (prefers-reduced-motion: reduce) { .guest-rise { animation: none; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@include('partials.theme-toggle', ['class' => 'fixed right-4 top-4 z-50 grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white/80 text-slate-600 shadow-sm backdrop-blur transition hover:text-slate-900'])
<div class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-12 sm:px-8">
    {{-- Арын өнгөт гэрэл ба тор --}}
    <div class="pointer-events-none absolute -left-40 -top-40 h-[30rem] w-[30rem] rounded-full bg-blue-400/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-48 -right-40 h-[32rem] w-[32rem] rounded-full bg-indigo-400/20 blur-3xl"></div>
    <div class="guest-grid pointer-events-none absolute inset-0"></div>

    <div class="guest-rise relative w-full max-w-[30rem]">
        <div class="mb-8 flex items-center justify-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-600/30 ring-1 ring-white/20">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
            </div>
            <span class="text-lg font-semibold tracking-tight text-slate-900">{{ config('app.name') }}</span>
        </div>

        <div class="rounded-3xl border border-slate-200/80 bg-white/85 p-7 shadow-xl shadow-slate-200/60 backdrop-blur sm:p-9">
            <div class="flex items-center gap-3.5">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600 ring-1 ring-blue-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15.5 7.5a3.5 3.5 0 11-4.95 3.18L3 18.25V21h2.75l.75-.75V18.5h1.75l.75-.75V16h1.75l1.57-1.57A3.5 3.5 0 0115.5 7.5zM16.5 9.5h.01"/></svg>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">@yield('title')</h1>
            </div>

            @if ($errors->any())
                <div class="mt-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 8v4M12 16h.01M12 21a9 9 0 110-18 9 9 0 010 18z"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif
            @if (session('status'))
                <div class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @yield('content')
        </div>

        <a href="{{ route('login') }}" class="group mx-auto mt-6 flex w-fit items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-blue-600 hover:shadow-sm">
            <svg class="h-4 w-4 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Нэвтрэх хуудас руу буцах
        </a>
    </div>
</div>
</body>
</html>
