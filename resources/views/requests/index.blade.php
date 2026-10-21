@extends('layouts.app')
@section('title', 'Хүсэлтүүд')

@php
    $statuses = \App\Http\Controllers\AssetRequestController::STATUSES;
    $badge = ['pending' => 'bg-amber-50 text-amber-700', 'in_progress' => 'bg-blue-50 text-blue-700',
              'done' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700'];
    $inp = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $queueNumbers = \App\Models\Computer::repairQueueNumbers();
@endphp

@section('content')
<div class="mb-5">
    <div class="text-xs text-slate-500">Удирдлага</div>
    <h1 class="!mb-0 text-xl font-bold">Ажилтнуудын хүсэлт</h1>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ url('/requests') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, ажилтан, төхөөрөмж, тайлбар..." class="{{ $inp }} min-w-60 flex-1">
    <select name="status" class="{{ $inp }}">
        <option value="">Бүх төлөв</option>
        @foreach ($statuses as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
    </select>
    <select name="type" class="{{ $inp }}">
        <option value="">Бүх төрөл</option>
        <option value="issue" @selected(request('type') === 'issue')>Эвдрэл / засвар</option>
        <option value="request" @selected(request('type') === 'request')>Төхөөрөмж захиалга</option>
    </select>
    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Хайх</button>
    @if (request()->hasAny(['status', 'type', 'q']))<a href="{{ url('/requests') }}" class="text-sm text-slate-500 hover:text-slate-800">Цэвэрлэх</a>@endif
</form>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="w-12">№</th><th>Огноо</th><th>Ажилтан</th><th>Төрөл</th><th>Төхөөрөмж / Гарчиг</th><th>Тайлбар</th><th>Төлөв ба хариу</th></tr></thead>
            <tbody>
            @forelse ($requests as $r)
                <tr class="align-top">
                    <td class="font-mono text-xs font-semibold text-slate-500">#{{ $requests->firstItem() + $loop->index }}</td>
                    <td class="whitespace-nowrap text-slate-600">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                    <td class="font-semibold">{{ $r->user->name ?? '—' }}</td>
                    <td>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $r->type === 'issue' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}">
                            {{ $r->type === 'issue' ? 'Эвдрэл' : 'Захиалга' }}
                        </span>
                    </td>
                    <td>
                        @if ($r->computer)<div class="font-medium">{{ trim($r->computer->brand . ' ' . $r->computer->model) ?: $r->computer->name }}</div>@endif
                        <div class="text-slate-600">{{ $r->subject ?: '—' }}</div>
                        @if ($r->type === 'issue' && $r->computer && in_array($r->status, ['pending', 'in_progress'], true))
                            @if ($r->computer->status === \App\Enums\ComputerStatus::InRepair)
                                <a href="{{ route('repairs.index') }}" class="mt-1 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold !text-amber-800">Засварт #{{ $queueNumbers[$r->computer->id] ?? '' }}</a>
                            @else
                                <form method="POST" action="{{ route('requests.repair', $r) }}" class="mt-1">@csrf
                                    <button class="!px-2.5 !py-1 text-xs !border-amber-300 !bg-amber-50 !text-amber-800">Засварын дараалалд оруулах</button>
                                </form>
                            @endif
                        @endif
                    </td>
                    <td class="max-w-xs text-slate-600">{{ $r->description }}</td>
                    <td>
                        <form method="POST" action="{{ url('/requests/' . $r->id) }}" class="flex min-w-64 flex-col gap-2">
                            @csrf @method('PATCH')
                            <select name="status" class="{{ $inp }}">
                                @foreach ($statuses as $k => $v)<option value="{{ $k }}" @selected($r->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                            <input type="text" name="admin_note" value="{{ $r->admin_note }}" maxlength="500" placeholder="Ажилтанд хариу бичих…" class="{{ $inp }}">
                            <button type="submit" class="self-start rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Хадгалах</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-slate-400">Хүсэлт байхгүй байна.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
        <span>Нийт {{ $requests->total() }}</span>{{ $requests->links() }}
    </div>
</section>
@endsection