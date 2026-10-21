@extends('layouts.app')
@section('title', 'Салбар')
@section('content')
@php $in = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm'; @endphp

<div class="mx-auto max-w-5xl space-y-4">
    <div>
        <h1 class="!mb-0 text-2xl font-bold">Салбар <span class="text-base font-normal text-slate-400">({{ $branches->count() }})</span></h1>
        <p class="mt-1 text-sm text-slate-500">
            Салбар бүрийн <b>Админ</b> зөвхөн тухайн салбарын төхөөрөмж, ажилтан, засвар, хүсэлтийг харж удирдана.
            Админд салбар оноохдоо <a href="{{ route('users.index') }}" class="!text-blue-600 hover:underline">Хэрэглэгч</a> хуудаснаас засна.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Шинэ салбар --}}
    <form method="POST" action="{{ route('branches.store') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[2fr_1fr_2fr_auto]">
        @csrf
        <input name="name" value="{{ old('name') }}" required placeholder="Салбарын нэр (жишээ: Дархан салбар)" class="{{ $in }}">
        <input name="code" value="{{ old('code') }}" placeholder="Код (DRH)" class="{{ $in }} uppercase">
        <input name="address" value="{{ old('address') }}" placeholder="Хаяг (заавал биш)" class="{{ $in }}">
        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ Салбар нэмэх</button>
    </form>

    {{-- Салбарууд --}}
    <div class="space-y-3">
        @foreach ($branches as $b)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-base font-bold text-slate-900">{{ $b->name }} @if ($b->code)<span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{{ $b->code }}</span>@endif</div>
                        @if ($b->address)<div class="text-xs text-slate-500">{{ $b->address }}</div>@endif
                    </div>
                    <div class="flex gap-4 text-center text-xs text-slate-500">
                        <a href="{{ route('computers.index', ['branch' => $b->id]) }}" class="!text-slate-500 hover:!text-blue-600"><b class="block text-lg text-slate-900">{{ $b->computers_count }}</b>төхөөрөмж</a>
                        <span><b class="block text-lg text-slate-900">{{ $b->employees_count }}</b>ажилтан</span>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-xs font-semibold text-slate-500">Хариуцах админ:</span>
                    @forelse ($b->admins as $a)
                        <a href="{{ route('users.edit', $a) }}" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium !text-blue-700 hover:bg-blue-100">{{ $a->name }}@unless ($a->is_active) (идэвхгүй)@endunless</a>
                    @empty
                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800">⚠ Админ оноогоогүй</span>
                        <a href="{{ route('users.create', ['role' => 'admin', 'branch_id' => $b->id]) }}" class="text-xs !text-blue-600 hover:underline">+ Админ үүсгэх</a>
                    @endforelse
                </div>

                <details class="mt-3 border-t border-slate-100 pt-3">
                    <summary class="cursor-pointer select-none text-xs font-medium text-slate-500 hover:text-slate-800">Засах / устгах</summary>
                    <div class="mt-2 flex flex-wrap items-start gap-2">
                        <form method="POST" action="{{ route('branches.update', $b) }}" class="grid flex-1 gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $b->name }}" required class="{{ $in }}">
                            <input name="code" value="{{ $b->code }}" placeholder="Код" class="{{ $in }} uppercase">
                            <input name="address" value="{{ $b->address }}" placeholder="Хаяг" class="{{ $in }}">
                            <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Хадгалах</button>
                        </form>
                        <form method="POST" action="{{ route('branches.destroy', $b) }}" onsubmit="return confirm('«{{ addslashes($b->name) }}» салбарыг устгах уу?')">
                            @csrf @method('DELETE')
                            <button class="btn-danger rounded-lg !border-rose-200 !bg-white px-4 py-2 text-sm !text-rose-600 hover:!bg-rose-50"
                                    @disabled($b->computers_count || $b->employees_count || $b->admins->isNotEmpty())
                                    title="Төхөөрөмж, хэрэглэгчгүй салбарыг л устгана">Устгах</button>
                        </form>
                    </div>
                </details>
            </div>
        @endforeach
    </div>
</div>
@endsection
