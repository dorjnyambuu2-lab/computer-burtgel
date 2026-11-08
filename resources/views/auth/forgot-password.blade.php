@extends('auth._guest')
@section('title', 'Нууц үг сэргээх')
@section('content')
<div class="mt-6 space-y-2.5">
    @foreach ([
        ['Супер админ', 'Бүртгэлтэй имэйл рүү сэргээх холбоос очно. Холбоосоор орж сэргээх түлхүүрээ оруулна.', 'M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z', 'bg-violet-50 text-violet-600 ring-violet-100'],
        ['Админ', 'Имэйлтэй бол сэргээх холбоос имэйлээр очно. Имэйлгүй бол Супер админ түр нууц үг олгоно.', 'M4 6h16v12H4zM4 7l8 6 8-6', 'bg-blue-50 text-blue-600 ring-blue-100'],
        ['Ажилтан', 'Хүсэлт салбарын Админ болон Супер админ руу илгээгдэж, аль нэг нь түр нууц үг олгоно.', 'M20 21a8 8 0 10-16 0M12 13a4 4 0 100-8 4 4 0 000 8z', 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
    ] as [$role, $text, $icon, $tone])
        <div class="flex gap-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl ring-1 {{ $tone }}">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
            </span>
            <div class="text-sm leading-relaxed">
                <div class="font-semibold text-slate-800">{{ $role }}</div>
                <div class="text-slate-500">{{ $text }}</div>
            </div>
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
    @csrf
    <div>
        <label for="login" class="mb-1.5 block text-sm font-medium text-slate-700">Нэвтрэх нэр эсвэл имэйл</label>
        <div class="group relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-3.6 7.2"/></svg>
            </span>
            <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                   placeholder="Нэвтрэх нэр эсвэл имэйлээ оруулна уу"
                   class="block w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-3.5 pl-11 pr-4 text-sm text-slate-900 transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus-visible:outline-none">
        </div>
    </div>
    <button type="submit"
            class="group flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-600/30 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:translate-y-0">
        Хүсэлт илгээх
        <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </button>
</form>
@endsection
