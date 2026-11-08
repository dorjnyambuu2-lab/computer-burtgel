<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нэвтрэх · {{ config('app.name') }}</title>
    @include('partials.theme')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes login-float { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(24px, -18px) scale(1.06); } }
        @keyframes login-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        .login-blob { animation: login-float 14s ease-in-out infinite; }
        .login-blob-2 { animation-delay: -5s; animation-duration: 18s; }
        .login-rise { animation: login-rise .5s ease-out both; }
        .login-grid {
            background-image: linear-gradient(rgb(255 255 255 / .06) 1px, transparent 1px),
                              linear-gradient(90deg, rgb(255 255 255 / .06) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at 30% 40%, #000 30%, transparent 75%);
        }
        @media (prefers-reduced-motion: reduce) { .login-blob, .login-rise { animation: none; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@include('partials.theme-toggle', ['class' => 'fixed right-4 top-4 z-50 grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white/80 text-slate-600 shadow-sm backdrop-blur transition hover:text-slate-900'])
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

    {{-- Зүүн тал: брэнд --}}
    <aside class="keep-light relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between xl:p-16">
        {{-- Арын өнгөт гэрэл ба тор --}}
        <div class="login-blob pointer-events-none absolute -left-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-blue-600/40 blur-3xl"></div>
        <div class="login-blob login-blob-2 pointer-events-none absolute -bottom-40 right-[-6rem] h-[30rem] w-[30rem] rounded-full bg-indigo-600/35 blur-3xl"></div>
        <div class="pointer-events-none absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-400/15 blur-3xl"></div>
        <div class="login-grid pointer-events-none absolute inset-0"></div>

        <div class="relative flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg shadow-blue-900/50 ring-1 ring-white/20">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
            </div>
            <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
        </div>

        <div class="relative max-w-lg">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-blue-100 backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px] shadow-emerald-400"></span>
                Тоног төхөөрөмжийн бүртгэлийн систем
            </span>
            <h2 class="mt-6 text-4xl font-bold leading-[1.15] tracking-tight xl:text-5xl">
                Тоног төхөөрөмжөө<br>
                <span class="bg-gradient-to-r from-sky-300 via-blue-300 to-indigo-300 bg-clip-text text-transparent">нэг дороос удирд.</span>
            </h2>
            <p class="mt-5 text-base leading-relaxed text-slate-300">Компьютер, хариуцагч, баталгаат хугацаа, засвар үйлчилгээний бүх мэдээлэл найдвартай, цэгцтэй хадгалагдана.</p>

            <div class="mt-10 grid gap-3">
                @foreach ([
                    ['Компьютерийн бүртгэл, хайлт, шүүлт', 'M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z'],
                    ['Баталгаа дуусах хугацааны сануулга', 'M12 8v4l2.5 2.5M12 21a9 9 0 110-18 9 9 0 010 18z'],
                    ['Үйлдлийн түүх, эрхийн удирдлага', 'M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3zM9 12l2 2 4-4'],
                ] as $i => [$text, $icon])
                    <div class="login-rise flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3.5 backdrop-blur-sm transition hover:border-white/20 hover:bg-white/[0.07]"
                         style="animation-delay: {{ 150 + $i * 100 }}ms">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-blue-500/30 to-indigo-500/30 text-blue-200 ring-1 ring-white/10">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                        </span>
                        <span class="text-sm font-medium text-slate-100">{{ $text }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div></div>
    </aside>

    {{-- Баруун тал: форм --}}
    <main class="relative flex items-center justify-center overflow-hidden px-5 py-12 sm:px-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgb(59_130_246/0.08),transparent_45%),radial-gradient(circle_at_bottom_left,rgb(99_102_241/0.07),transparent_40%)]"></div>

        <div class="login-rise relative w-full max-w-[26rem]">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <div class="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-600/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
                </div>
                <span class="font-semibold tracking-tight">{{ config('app.name') }}</span>
            </div>

            <div class="rounded-3xl border border-slate-200/80 bg-white/80 p-7 shadow-xl shadow-slate-200/60 backdrop-blur sm:p-9">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[1.7rem]">Тавтай морил</h1>

                @if ($errors->any())
                    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 8v4M12 16h.01M12 21a9 9 0 110-18 9 9 0 010 18z"/></svg>
                        <span>{{ str_contains($errors->first(), 'required') ? 'Нэвтрэх нэр болон нууц үгээ оруулна уу.' : $errors->first() }}</span>
                    </div>
                @endif
                @if (session('status'))
                    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="mt-7 space-y-5" id="login-form">
                    @csrf

                    <div>
                        <label for="login" class="mb-1.5 block text-sm font-medium text-slate-700">Нэвтрэх нэр</label>
                        <div class="group relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21a8 8 0 10-16 0M12 13a4 4 0 100-8 4 4 0 000 8z"/></svg>
                            </span>
                            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                                   placeholder="Нэвтрэх нэрээ оруулна уу"
                                   class="block w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-3.5 pl-11 pr-4 text-sm text-slate-900 transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus-visible:outline-none">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Нууц үг</label>
                        <div class="group relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
                            </span>
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                   placeholder="Нууц үгээ оруулна уу"
                                   class="block w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-3.5 pl-11 pr-12 text-sm text-slate-900 transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus-visible:outline-none">
                            {{-- Харах / нуух: нүд, зураастай нүд --}}
                            <button type="button" id="toggle-pw" title="Нууц үг харах" aria-label="Нууц үг харах"
                                    class="absolute inset-y-0 right-0 flex items-center rounded-r-2xl px-4 text-slate-400 transition hover:text-slate-700">
                                <svg data-eye viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg data-eye-off viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M17.9 17.9A10.4 10.4 0 0112 19c-6.5 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A9.6 9.6 0 0112 5c6.5 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M14.1 14.1a3 3 0 11-4.2-4.2M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <p id="caps-warn" class="mt-1.5 hidden text-xs font-medium text-amber-600">Caps Lock асаалттай байна.</p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label class="flex cursor-pointer select-none items-center gap-2.5 text-sm text-slate-600">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 accent-blue-600"> Намайг сана
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-600 transition hover:text-blue-700 hover:underline">Нууц үгээ мартсан?</a>
                    </div>

                    <button type="submit" id="login-submit"
                            class="group relative flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-600/30 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:translate-y-0 disabled:cursor-wait disabled:opacity-80">
                        <svg data-spin class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        <span data-label>Нэвтрэх</span>
                        <svg data-arrow class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
    document.getElementById('toggle-pw').addEventListener('click', function () {
        var i = document.getElementById('password');
        var show = i.type === 'password';
        i.type = show ? 'text' : 'password';
        this.querySelector('[data-eye]').style.display = show ? 'none' : '';
        this.querySelector('[data-eye-off]').style.display = show ? '' : 'none';
        this.title = show ? 'Нууц үг нуух' : 'Нууц үг харах';
        this.setAttribute('aria-label', this.title);
    });

    // Caps Lock анхааруулга
    ['keydown', 'keyup'].forEach(function (ev) {
        document.getElementById('password').addEventListener(ev, function (e) {
            if (e.getModifierState) {
                document.getElementById('caps-warn').classList.toggle('hidden', !e.getModifierState('CapsLock'));
            }
        });
    });

    // Илгээж байх үед давхар дарахаас сэргийлж ачаалж буйг харуулна
    document.getElementById('login-form').addEventListener('submit', function () {
        var b = document.getElementById('login-submit');
        b.disabled = true;
        b.querySelector('[data-spin]').classList.remove('hidden');
        b.querySelector('[data-arrow]').classList.add('hidden');
        b.querySelector('[data-label]').textContent = 'Нэвтэрч байна…';
    });
    // «Буцах» товчоор эргэж ирэхэд (bfcache) товчийг хэвийн болгоно
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var b = document.getElementById('login-submit');
        b.disabled = false;
        b.querySelector('[data-spin]').classList.add('hidden');
        b.querySelector('[data-arrow]').classList.remove('hidden');
        b.querySelector('[data-label]').textContent = 'Нэвтрэх';
    });
</script>
</body>
</html>
