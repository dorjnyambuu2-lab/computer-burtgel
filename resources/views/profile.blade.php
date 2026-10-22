@extends('layouts.app')

@section('title', 'Миний профайл')

@php
    $in = 'w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $fullName = trim($user->surname.' '.$user->name);
    // Ажлын мэдээллийг Админ бүртгэнэ — энд зөвхөн харагдана
    $job = [
        'Салбар'       => $user->isSuperadmin() ? 'Бүх салбар' : $user->branch?->name,
        'Нэгж'         => $user->unit,
        'Алба'         => $user->department,
        'Албан тушаал' => $user->position,
        'Эрх'          => $user->roleLabel(),
    ];
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-blue-100 text-xl font-bold text-blue-700">
            {{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}
        </div>
        <div class="min-w-0">
            <div class="text-lg font-bold">{{ $fullName }}</div>
            @if ($user->department || $user->position)
                <div class="text-sm text-slate-600">{{ collect([$user->department, $user->position])->filter()->implode(' · ') }}</div>
            @endif
            <div class="text-sm text-slate-500">{{ $user->email }}</div>
            <span class="mt-1 inline-block rounded-full bg-blue-50 px-3 py-0.5 text-xs font-medium text-blue-700">
                {{ $user->roleLabel() }}{{ $job['Салбар'] ? ' · '.$job['Салбар'] : '' }}
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('profile.update') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <h2 class="font-semibold">Хувийн мэдээлэл</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="surname" class="mb-1 block text-sm text-slate-600">Овог</label>
                <input id="surname" type="text" name="surname" value="{{ old('surname', $user->surname) }}" maxlength="255" placeholder="Жишээ: Батын" class="{{ $in }}">
                @error('surname') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="name" class="mb-1 block text-sm text-slate-600">Нэр</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required class="{{ $in }}">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm text-slate-600">Имэйл</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="{{ $in }}">
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('my.password.edit') }}" class="text-sm text-blue-600 hover:underline">Нууц үг солих</a>
            <button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">Хадгалах</button>
        </div>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold">Ажлын мэдээлэл</h2>
        <p class="mb-4 mt-1 text-xs text-slate-500">
            {{ $user->isSuperadmin() ? 'Хэрэглэгчийн хуудаснаас засна.' : 'Админ бүртгэнэ. Өөрчлөх шаардлагатай бол салбарынхаа админд хандана уу.' }}
        </p>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            @foreach ($job as $label => $value)
                <div>
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 font-medium {{ filled($value) ? 'text-slate-900' : 'text-slate-400' }}">{{ filled($value) ? $value : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
