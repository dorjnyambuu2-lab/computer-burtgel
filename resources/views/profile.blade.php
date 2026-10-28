@extends('layouts.app')

@section('title', 'Миний профайл')

@php
    $in = 'w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $fullName = trim($user->surname.' '.$user->name);
    // Ажлын мэдээллийг Админ бүртгэнэ — энд зөвхөн харагдана
    $job = [
        $user->idLabel() => $user->idCode(),
        'Салбарын ID'  => $user->isSuperadmin() ? null : $user->branch?->idCode(),
        'Салбар'       => $user->isSuperadmin() ? 'Бүх салбар' : $user->branch?->name,
        'Нэгж'         => $user->unit,
        'Алба'         => $user->department,
        'Албан тушаал' => $user->position,
        'Эрх'          => $user->roleLabel(),
    ];
    // Толгойд: алба · албан тушаал (албан тушаал нь эрхтэйгээ ижил бол давтахгүй: «супер админ»)
    $subtitle = collect([$user->department, $user->position])
        ->filter(fn ($v) => filled($v) && mb_strtolower(trim($v)) !== mb_strtolower($user->roleLabel()))
        ->implode(' · ');
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-blue-100 text-xl font-bold text-blue-700">
            {{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}
        </div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-lg font-bold">{{ $fullName }}
                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-slate-600" title="{{ $user->idLabel() }}">{{ $user->idCode() }}</span>
            </div>
            @if ($subtitle !== '')
                <div class="text-sm text-slate-600">{{ $subtitle }}</div>
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
            {{-- Супер админд «Бүртгэл» картад байна --}}
            @unless ($user->isSuperadmin())
                <a href="{{ route('my.password.edit') }}" class="text-sm text-blue-600 hover:underline">Нууц үг солих</a>
            @endunless
            <button class="ml-auto rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">Хадгалах</button>
        </div>
    </form>

    @if ($user->isSuperadmin())
    {{-- Супер админ: ажлын мэдээлэл (нэгж, алба, салбар) хамаарахгүй, толгойд аль хэдийн байгаа тул нэвтрэлтийн мэдээлэл --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 font-semibold">Бүртгэл</h2>
        <dl class="divide-y divide-slate-100 text-sm">
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Нэвтрэх нэр</dt>
                <dd class="font-mono font-medium text-slate-900">{{ $user->username ?: '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Сүүлд нэвтэрсэн</dt>
                <dd class="font-medium text-slate-900">{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-slate-500">Нууц үг</dt>
                <dd><a href="{{ route('my.password.edit') }}" class="inline-flex items-center gap-1 text-sm font-semibold !text-blue-600 hover:underline">Солих
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a></dd>
            </div>
        </dl>
    </section>
    @else
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 font-semibold">Ажлын мэдээлэл</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            @foreach ($job as $label => $value)
                @continue($label === 'Салбарын ID' && $user->isSuperadmin())
                <div>
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 font-medium {{ filled($value) ? 'text-slate-900' : 'text-slate-400' }} {{ str_ends_with($label, 'ID') ? 'font-mono' : '' }}">{{ filled($value) ? $value : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
    @endif
</div>
@endsection