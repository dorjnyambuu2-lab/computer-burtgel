@extends('layouts.app')
@section('title', 'Засвар')
@section('content')
@php
    $sel = 'h-10 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $current = (string) request('stage');
    // Явцын табууд (тоотой): Бүгд + явц бүр
    $tabs = ['' => ['Бүгд', $total]];
    foreach ($stages as $k => $l) {
        $tabs[$k] = [$l, (int) ($stageCounts[$k] ?? 0)];
    }
    $filtered = filled(request('q'));
@endphp

<div class="mb-5">
    <h1 class="!mb-0 text-2xl font-bold text-slate-900">Засвар</h1>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
@endif

{{-- Явцын табууд + хайлт --}}
<form method="GET" class="mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
    @if ($current !== '')<input type="hidden" name="stage" value="{{ $current }}">@endif
    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 p-2">
        @foreach ($tabs as $value => [$label, $count])
            @php $on = $current === (string) $value; @endphp
            <a href="{{ request()->fullUrlWithQuery(['stage' => $value ?: null]) }}"
               class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $on ? 'bg-slate-900 !text-white' : '!text-slate-600 hover:bg-slate-100' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $on ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-2 p-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: дугаар, нэр, серийн, хариуцагч..." class="{{ $sel }} min-w-60 flex-1">
        <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хайх</button>
        @if ($filtered)<a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm !text-slate-500 hover:bg-slate-100">Цэвэрлэх</a>@endif
    </div>
</form>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="w-16 px-4 py-3">Дараалал</th>
                <th class="px-4 py-3">Төхөөрөмж ба эвдрэл</th>
                <th class="px-4 py-3">Хариуцагч</th>
                <th class="px-4 py-3">Явц</th>
                <th class="px-4 py-3"><span class="sr-only">Үйлдэл</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($computers as $c)
            @php $n = $numbers[$c->id] ?? null; @endphp
            <tr class="align-top transition hover:bg-slate-50/60">
                <td class="px-4 py-3">
                    <span class="inline-block min-w-10 rounded-lg bg-amber-100 px-2 py-1 text-center font-mono text-sm font-bold text-amber-800">#{{ $n }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <a href="{{ route('computers.show', $c) }}" class="font-semibold !text-slate-900 hover:!text-blue-700 hover:underline">{{ $c->name ?: 'Компьютер' }}</a>
                        <span class="whitespace-nowrap font-mono text-[11px] text-slate-400">{{ $c->inventory_number }}</span>
                    </div>
                    {{-- Эвдрэлийн тайлбар (засварт бүртгэхэд бичсэн), мэдээлсэн ажилтан --}}
                    @if ($c->repair_reason)<div class="mt-1 max-w-xl text-slate-700">«{{ $c->repair_reason }}»</div>@endif
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-400">
                        @if ($c->repair_queued_at)<span title="{{ $c->repair_queued_at->format('Y-m-d H:i') }}">{{ $c->repair_queued_at->locale('mn')->diffForHumans() }} орсон</span>@endif
                        @if ($c->reported_by)<span>Мэдээлсэн: <span class="text-slate-600">{{ $c->reported_by }}</span></span>@endif
                        @if ($c->repair_cost_sum > 0)<span class="text-amber-700">Нийт зардал: {{ \App\Support\Money::format($c->repair_cost_sum) }}</span>@endif
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3 {{ ($c->assignedUser?->name ?? $c->assigned_to) ? 'text-slate-700' : 'text-slate-300' }}">{{ $c->assignedUser?->name ?? $c->assigned_to ?? '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $c->repair_stage?->badgeClasses() ?? 'bg-slate-100 text-slate-700' }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $c->repair_stage?->label() ?? 'Дараалалд' }}</span>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <div class="flex items-start justify-end gap-1.5">
                        {{-- Дуусгах: хамгийн их хэрэглэгддэг үйлдэл --}}
                        <details class="relative" data-popover>
                            <summary class="flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg bg-emerald-600 px-2.5 text-xs font-semibold text-white hover:bg-emerald-700 [&::-webkit-details-marker]:hidden">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
                                Дуусгах
                            </summary>
                            <form method="POST" action="{{ route('repairs.update', $c) }}" data-popover-panel class="absolute right-0 z-10 mt-1 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                @csrf @method('PATCH')
                                <input type="hidden" name="stage" value="done">
                                <label class="block text-xs font-medium text-slate-500">Юу хийсэн бэ
                                    <input type="text" name="note" maxlength="1000" placeholder="Жишээ: Дэлгэцийн кабель сольсон" class="{{ $sel }} mt-1 block w-full">
                                </label>
                                <label class="block text-xs font-medium text-slate-500">Зардал ₮ (заавал биш)
                                    <input type="text" name="cost" inputmode="decimal" data-money autocomplete="off" placeholder="Жишээ: 150,000" class="{{ $sel }} mt-1 block w-full">
                                </label>
                                <button type="submit" class="h-9 w-full !min-h-0 !rounded-lg !border-emerald-600 !bg-emerald-600 !px-3 !py-0 text-sm font-semibold !text-white hover:!bg-emerald-700">Засвар дууссан</button>
                            </form>
                        </details>
                        {{-- Явц шинэчлэх --}}
                        <details class="relative" data-popover>
                            <summary class="flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                Явц
                            </summary>
                            <form method="POST" action="{{ route('repairs.update', $c) }}" data-popover-panel class="absolute right-0 z-10 mt-1 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                @csrf @method('PATCH')
                                <label class="block text-xs font-medium text-slate-500">Явц
                                    <select name="stage" class="{{ $sel }} mt-1 block w-full">
                                        @foreach ($stages as $k => $l)<option value="{{ $k }}" @selected(($c->repair_stage?->value ?? 'queued') === $k)>{{ $l }}</option>@endforeach
                                    </select>
                                </label>
                                <label class="block text-xs font-medium text-slate-500">Тайлбар (заавал биш)
                                    <input type="text" name="note" maxlength="1000" class="{{ $sel }} mt-1 block w-full">
                                </label>
                                <label class="block text-xs font-medium text-slate-500">Зардал ₮ (заавал биш)
                                    <input type="text" name="cost" inputmode="decimal" data-money autocomplete="off" placeholder="Сэлбэг, ажлын хөлс" class="{{ $sel }} mt-1 block w-full">
                                </label>
                                <button type="submit" class="h-9 w-full !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хадгалах</button>
                            </form>
                        </details>
                        {{-- Дарааллын байр --}}
                        @foreach (['up' => 'M6 15l6-6 6 6', 'down' => 'M6 9l6 6 6-6'] as $dir => $path)
                            <form method="POST" action="{{ route('repairs.move', $c) }}">
                                @csrf <input type="hidden" name="direction" value="{{ $dir }}">
                                <button title="{{ $dir === 'up' ? 'Дараалалд дээш' : 'Дараалалд доош' }}" aria-label="{{ $dir === 'up' ? 'Дээш' : 'Доош' }}"
                                        class="flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border !border-slate-200 !bg-white !p-0 !text-slate-500 hover:!bg-slate-100 disabled:opacity-30"
                                        @disabled(($dir === 'up' && $n === 1) || ($dir === 'down' && $n === $total))>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-slate-400">{{ $filtered || $current !== '' ? 'Шүүлтэд тохирох төхөөрөмж алга.' : 'Засварт байгаа төхөөрөмж алга.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</section>
@endsection
