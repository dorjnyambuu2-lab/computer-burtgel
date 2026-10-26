@extends('auth._guest')
@section('title', 'Нууц үг сэргээх')
@section('content')
@php $in = 'mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100'; @endphp
<ul class="mt-3 space-y-1 text-sm text-slate-500">
    <li>• <b>Супер админ:</b> бүртгэлтэй имэйл рүү сэргээх холбоос очно. Холбоосоор орж сэргээх түлхүүрээ оруулна.</li>
    <li>• <b>Админ:</b> хүсэлт Супер админ руу автоматаар илгээгдэж, Супер админ түр нууц үг олгоно.</li>
    <li>• <b>Ажилтан:</b> хүсэлт салбарын Админ болон Супер админ руу илгээгдэж, аль нэг нь түр нууц үг олгоно.</li>
</ul>

<form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
    @csrf
    <label class="block text-sm font-medium text-slate-700">Нэвтрэх нэр эсвэл имэйл
        <input name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" class="{{ $in }}">
    </label>
    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Хүсэлт илгээх</button>
</form>
@endsection
