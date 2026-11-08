@extends('auth._guest')
@section('title', 'Шинэ нууц үг тохируулах')
@section('content')
@php
    $in = 'block w-full rounded-2xl border border-slate-200 bg-slate-50/70 py-3.5 pl-11 pr-4 text-sm text-slate-900 transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus-visible:outline-none';
    $ic = 'pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600';
    $lock = '<svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>';
@endphp
<form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Имэйл</label>
        <div class="group relative">
            <span class="{{ $ic }}"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg></span>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="{{ $in }}">
        </div>
    </div>

    @if ($needsRecoveryKey)
        <div>
            <label for="recovery_key" class="mb-1.5 block text-sm font-medium text-slate-700">Сэргээх түлхүүр</label>
            <div class="group relative">
                <span class="{{ $ic }}"><svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15.5 7.5a3.5 3.5 0 11-4.95 3.18L3 18.25V21h2.75l.75-.75V18.5h1.75l.75-.75V16h1.75l1.57-1.57A3.5 3.5 0 0115.5 7.5z"/></svg></span>
                <input id="recovery_key" name="recovery_key" type="password" required autocomplete="off" class="{{ $in }}">
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Системийг суулгахад тохируулсан нууц түлхүүр (SUPERADMIN_RECOVERY_KEY).</p>
        </div>
    @endif

    <div>
        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Шинэ нууц үг</label>
        <div class="group relative">
            <span class="{{ $ic }}">{!! $lock !!}</span>
            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Хамгийн багадаа 8 тэмдэгт" class="{{ $in }}">
        </div>
    </div>
    <div>
        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Шинэ нууц үг давтах</label>
        <div class="group relative">
            <span class="{{ $ic }}">{!! $lock !!}</span>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Нууц үгээ дахин оруулна уу" class="{{ $in }}">
        </div>
    </div>

    <button type="submit"
            class="group flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-600/30 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:translate-y-0">
        Нууц үг шинэчлэх
        <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
    </button>
</form>
@endsection
