@extends('auth._guest')
@section('title', 'Шинэ нууц үг тохируулах')
@section('content')
@php $in = 'mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100'; @endphp
<form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <label class="block text-sm font-medium text-slate-700">Имэйл
        <input name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" class="{{ $in }}">
    </label>

    @if ($needsRecoveryKey)
        <label class="block text-sm font-medium text-slate-700">Сэргээх түлхүүр
            <input name="recovery_key" type="password" required autocomplete="off" class="{{ $in }}">
            <span class="mt-1 block text-xs font-normal text-slate-500">Системийг суулгахад тохируулсан нууц түлхүүр (SUPERADMIN_RECOVERY_KEY).</span>
        </label>
    @endif

    <label class="block text-sm font-medium text-slate-700">Шинэ нууц үг (8+ тэмдэгт)
        <input name="password" type="password" required autocomplete="new-password" class="{{ $in }}">
    </label>
    <label class="block text-sm font-medium text-slate-700">Шинэ нууц үг давтах
        <input name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $in }}">
    </label>

    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Нууц үг шинэчлэх</button>
</form>
@endsection
