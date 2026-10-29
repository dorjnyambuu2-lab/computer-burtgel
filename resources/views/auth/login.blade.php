<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нэвтрэх · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">

    {{-- Зүүн тал: брэнд --}}
    <div class="relative hidden overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-xl bg-white/15 ring-1 ring-white/30">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
            </div>
            <span class="text-lg font-bold">{{ config('app.name') }}</span>
        </div>

        <div>
            <h2 class="text-4xl font-bold leading-tight">Тоног төхөөрөмжөө<br>нэг дороос удирд.</h2>
            <p class="mt-4 max-w-md text-blue-100">Компьютер, хариуцагч, баталгаат хугацаа, засвар үйлчилгээний бүх мэдээлэл найдвартай, цэгцтэй хадгалагдана.</p>
            <ul class="mt-8 space-y-3 text-sm text-blue-50">
                <li class="flex items-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-white/20">✓</span> Компьютерийн бүртгэл, хайлт, шүүлт</li>
                <li class="flex items-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-white/20">✓</span> Баталгаа дуусах хугацааны сануулга</li>
                <li class="flex items-center gap-3"><span class="grid h-6 w-6 place-items-center rounded-full bg-white/20">✓</span> Үйлдлийн түүх, эрхийн удирдлага</li>
            </ul>
        </div>

        <div class="text-xs text-blue-200">© {{ date('Y') }} {{ config('app.name') }}</div>

        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/10"></div>
    </div>

    {{-- Баруун тал: форм --}}
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H4zM8 20h8M12 16v4"/></svg>
                </div>
                <span class="font-bold">{{ config('app.name') }}</span>
            </div>

            <h1 class="text-2xl font-bold">Тавтай морил</h1>
            <p class="mt-1 text-sm text-slate-500">Системд нэвтрэхийн тулд мэдээллээ оруулна уу.</p>

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ str_contains($errors->first(), 'required') ? 'Нэвтрэх нэр болон нууц үгээ оруулна уу.' : $errors->first() }}
                </div>
            @endif
            @if (session('status'))
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="login" class="block text-sm font-medium text-slate-700">Нэвтрэх нэр</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                           placeholder="Нэвтрэх нэрээ оруулна уу"
                           class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Нууц үг</label>
                    <div class="relative mt-1.5">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="Нууц үгээ оруулна уу"
                               class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
                        {{-- Харах / нуух: нүд, зураастай нүд --}}
                        <button type="button" id="toggle-pw" title="Нууц үг харах" aria-label="Нууц үг харах"
                                class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-slate-700">
                            <svg data-eye viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-eye-off viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M17.9 17.9A10.4 10.4 0 0112 19c-6.5 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A9.6 9.6 0 0112 5c6.5 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M14.1 14.1a3 3 0 11-4.2-4.2M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-blue-600"> Намайг сана
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-600 hover:underline">Нууц үгээ мартсан?</a>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">
                    Нэвтрэх
                </button>
            </form>
        </div>
    </div>
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
</script>
</body>
</html>