@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-6 mt-6">
    <h1 class="text-xl font-bold mb-4">Нууц үг солих</h1>

    @if (auth()->user()->must_change_password && ! session('warning'))
        <div class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-800">Та анхны эсвэл түр нууц үгээр нэвтэрсэн байна. Шинэ нууц үг тохируулна уу.</div>
    @endif

    <form method="POST" action="{{ route('my.password.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm mb-1">Одоогийн нууц үг</label>
            <input type="password" name="current_password" required class="w-full border rounded-lg px-3 py-2">
            @error('current_password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Шинэ нууц үг (8+ тэмдэгт)</label>
            <input type="password" name="password" required class="w-full border rounded-lg px-3 py-2">
            @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Шинэ нууц үг давтах</label>
            <input type="password" name="password_confirmation" required class="w-full border rounded-lg px-3 py-2">
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white rounded-lg py-2 font-medium">Солих</button>
    </form>
</div>
@endsection