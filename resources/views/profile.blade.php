@extends('layouts.app')

@section('title', 'Миний профайл')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid h-16 w-16 place-items-center rounded-full bg-blue-100 text-xl font-bold text-blue-700">
            {{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}
        </div>
        <div>
            <div class="text-lg font-bold">{{ $user->name }}</div>
            <div class="text-sm text-slate-500">{{ $user->email }}</div>
            <span class="mt-1 inline-block rounded-full bg-blue-50 px-3 py-0.5 text-xs font-medium text-blue-700">
                {{ $user->roleLabel() }}
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('profile.update') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <h2 class="font-semibold">Хувийн мэдээлэл</h2>

        <div>
            <label class="mb-1 block text-sm text-slate-600">Нэр</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm text-slate-600">Имэйл</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm text-slate-600">Эрх</label>
            <input type="text" value="{{ $user->roleLabel() }}" disabled
                   class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500">
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('my.password.edit') }}" class="text-sm text-blue-600 hover:underline">Нууц үг солих</a>
            <button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">Хадгалах</button>
        </div>
    </form>
</div>
@endsection