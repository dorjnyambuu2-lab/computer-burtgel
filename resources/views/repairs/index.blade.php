@extends('layouts.app')
@section('title', 'Засвар')
@section('content')
@php
    $filtered = filled(request('q')) || filled(request('stage'));
    $inp = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
@endphp

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="!mb-0 text-2xl font-bold">Засвар <span class="text-base font-normal text-slate-400">({{ $total }})</span></h1>    </div>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
@endif

<form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: дугаар, нэр, серийн, хариуцагч..." class="{{ $inp }} min-w-0 flex-1">
    <select name="stage" class="{{ $inp }}">
        <option value="">Бүх явц</option>
        @foreach ($stages as $k => $l)<option value="{{ $k }}" @selected(request('stage') === $k)>{{ $l }}</option>@endforeach
    </select>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Шүүх</button>
    @if ($filtered)<a href="{{ route('repairs.index') }}" class="self-center text-sm text-slate-500 hover:text-slate-800">Цэвэрлэх</a>@endif
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Дугаар</th>
                <th class="px-4">Компьютер</th>
                <th class="px-4">Хариуцагч</th>
                <th class="px-4">Дараалалд орсон</th>
                <th class="px-4">Явц</th>
                <th class="px-4">Явц шинэчлэх</th>
                <th class="px-4 text-right">Байр</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($computers as $c)
            @php $n = $numbers[$c->id] ?? null; @endphp
            <tr class="align-top hover:bg-slate-50">
                <td class="px-4 py-3">
                    <span class="inline-block min-w-10 rounded-lg bg-amber-100 px-2 py-1 text-center font-mono text-sm font-bold text-amber-800">#{{ $n }}</span>
                </td>
                <td class="px-4 py-3">
                    <a href="{{ route('computers.show', $c) }}" class="font-medium text-indigo-600 hover:underline">{{ $c->name ?: 'Компьютер' }}</a>
                    <div class="font-mono text-xs text-slate-500">{{ $c->inventory_number }}</div>
                </td>
                <td class="px-4 py-3">{{ $c->assignedUser?->name ?? $c->assigned_to ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                    {{ $c->repair_queued_at?->format('Y-m-d H:i') ?? '—' }}
                    @if ($c->repair_queued_at)<div class="text-xs text-slate-400">{{ $c->repair_queued_at->locale('mn')->diffForHumans() }}</div>@endif
                </td>
                <td class="px-4 py-3">
                    <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs {{ $c->repair_stage?->badgeClasses() ?? 'bg-slate-100 text-slate-600' }}">{{ $c->repair_stage?->label() ?? 'Дараалалд' }}</span>
                </td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('repairs.update', $c) }}" class="flex flex-wrap gap-2">
                        @csrf @method('PATCH')
                        <select name="stage" class="{{ $inp }} !py-1.5" aria-label="Явц">
                            @foreach ($stages as $k => $l)<option value="{{ $k }}" @selected($c->repair_stage?->value === $k)>{{ $l }}</option>@endforeach
                            <option value="done">✓ Засвар дууссан</option>
                        </select>
                        <input type="text" name="note" maxlength="1000" placeholder="Тайлбар (заавал биш)" class="{{ $inp }} !py-1.5 min-w-40 flex-1">
                        <input type="text" name="cost" inputmode="decimal" placeholder="Зардал ₮" title="Засварын зардал (сэлбэг, ажлын хөлс)" class="{{ $inp }} !py-1.5 w-28">
                        @if ($c->repair_cost_sum > 0)
                            <span class="self-center whitespace-nowrap text-xs text-amber-700" title="Энэ компьютерийн нийт засварын зардал">Нийт: {{ \App\Support\Money::format($c->repair_cost_sum) }}</span>
                        @endif
                        <button class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Хадгалах</button>
                    </form>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    @foreach (['up' => '▲', 'down' => '▼'] as $dir => $arrow)
                        <form method="POST" action="{{ route('repairs.move', $c) }}" class="inline">
                            @csrf <input type="hidden" name="direction" value="{{ $dir }}">
                            <button title="{{ $dir === 'up' ? 'Дээш' : 'Доош' }}" class="!px-2.5 !py-1 text-xs"
                                @disabled(($dir === 'up' && $n === 1) || ($dir === 'down' && $n === $total))>{{ $arrow }}</button>
                        </form>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">{{ $filtered ? 'Шүүлтэд тохирох компьютер алга.' : 'Засварт байгаа компьютер алга.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
