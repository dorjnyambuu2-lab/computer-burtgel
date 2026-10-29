@extends('layouts.app')
@section('title', 'Алба хэлтэс')
@section('content')
@php
    $in = 'h-10 w-full !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $isSuper = auth()->user()->isSuperadmin();
    // Салбар бүрээр бүлэглэнэ (Админд ганц салбар)
    $groups = $departments->groupBy(fn ($d) => $d->branch?->name ?? 'Салбаргүй');
@endphp

<div class="mx-auto max-w-5xl space-y-4">
    <div>
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Алба хэлтэс <span class="text-base font-normal text-slate-400">({{ $departments->count() }})</span></h1>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Шинэ алба --}}
    <form method="POST" action="{{ route('departments.store') }}"
          class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm {{ $isSuper ? 'sm:grid-cols-[2fr_1fr_auto]' : 'sm:grid-cols-[1fr_auto]' }}">
        @csrf
        <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Албаны нэр (жишээ: Мэдээллийн технологийн алба)" class="{{ $in }}">
        @if ($isSuper)
            <select name="branch_id" required class="{{ $in }}">
                <option value="">— Салбар —</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) old('branch_id', request('branch')) === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        @endif
        <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-4 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">+ Алба нэмэх</button>
    </form>

    @forelse ($groups as $branchName => $list)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($isSuper)
                <h2 class="!mb-0 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $branchName }} <span class="ml-1 font-normal normal-case tracking-normal text-slate-400">· {{ $list->count() }} алба</span></h2>
            @endif
            <ul class="divide-y divide-slate-100">
                @foreach ($list as $d)
                    @php $siblings = $list->where('id', '!=', $d->id); $used = $d->computers_count || $d->users_count; @endphp
                    <li class="px-5 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="min-w-0 flex-1 font-semibold text-slate-900">{{ $d->name }}</span>
                            <a href="{{ route('computers.index', array_filter(['department' => $d->name, 'branch' => $isSuper ? $d->branch_id : null])) }}"
                               class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs !text-slate-600 hover:bg-slate-200">{{ $d->computers_count }} төхөөрөмж</a>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $d->users_count }} ажилтан</span>
                            <details class="group relative">
                                <summary class="flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 [&::-webkit-details-marker]:hidden">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                    Засах
                                </summary>
                                <div class="absolute right-0 z-10 mt-1 w-80 space-y-3 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                    {{-- Нэр солих: төхөөрөмж, ажилтан дээр шууд солигдоно --}}
                                    <form method="POST" action="{{ route('departments.update', $d) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $d->name }}" required maxlength="255" class="{{ $in }}" aria-label="Шинэ нэр">
                                        <button class="h-10 shrink-0 !min-h-0 !rounded-lg !border-slate-800 !bg-slate-800 !px-3 !py-0 text-xs !text-white">Солих</button>
                                    </form>
                                    {{-- Нэгтгэх: үсгийн алдаатай давхардлыг зөв алба руу --}}
                                    @if ($siblings->isNotEmpty())
                                        <form method="POST" action="{{ route('departments.merge', $d) }}" class="flex gap-2"
                                              onsubmit="return confirm(@js('«'.$d->name.'»-ын төхөөрөмж, ажилтныг сонгосон алба руу шилжүүлж, энэ албыг устгах уу?'))">
                                            @csrf
                                            <select name="target_id" required class="{{ $in }}" aria-label="Нэгтгэх алба">
                                                <option value="">Нэгтгэх алба…</option>
                                                @foreach ($siblings as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                            </select>
                                            <button class="h-10 shrink-0 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-0 text-xs !text-white">Нэгтгэх</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('departments.destroy', $d) }}" onsubmit="return confirm(@js('«'.$d->name.'» албыг устгах уу?'))">
                                        @csrf @method('DELETE')
                                        <button @disabled($used) title="{{ $used ? 'Төхөөрөмж, ажилтантай албыг устгахгүй — нэгтгэнэ үү' : 'Устгах' }}"
                                                class="w-full !min-h-0 !rounded-lg !border !border-rose-200 !bg-white !px-3 !py-2 text-xs !text-rose-600 hover:!bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Устгах</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-400">Алба хэлтэс бүртгэгдээгүй байна.</p>
    @endforelse
</div>
@endsection
