@extends('layouts.app')
@section('title', 'Ажилтны хүсэлт')

@php
    $statuses = \App\Http\Controllers\AssetRequestController::STATUSES;
    $badge = ['pending' => 'bg-amber-100 text-amber-800', 'in_progress' => 'bg-blue-100 text-blue-700',
              'done' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'];
    $sel = 'h-10 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $queueNumbers = \App\Models\Computer::repairQueueNumbers();
    $current = (string) request('status');
    // Төлөвийн табууд: Бүгд + төлөв бүр (тоотой). Хүлээгдэж буйг тодруулна
    $tabs = ['' => ['Бүгд', array_sum($statusCounts)]];
    foreach ($statuses as $k => $v) {
        $tabs[$k] = [$v, (int) ($statusCounts[$k] ?? 0)];
    }
@endphp

@section('content')
<div class="mb-5">
    <h1 class="!mb-0 text-2xl font-bold text-slate-900">Ажилтны хүсэлт</h1>
    @if ($statusCounts['pending'] ?? 0)
        <p class="mt-1 text-sm text-amber-700"><b>{{ $statusCounts['pending'] }}</b> хүсэлт шийдвэрлэхийг хүлээж байна</p>
    @endif
</div>

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
@endif

{{-- Төлөвийн табууд + хайлт, төрөл --}}
<form method="GET" action="{{ url('/requests') }}" class="mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
    @if ($current !== '')<input type="hidden" name="status" value="{{ $current }}">@endif
    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 p-2">
        @foreach ($tabs as $value => [$label, $count])
            @php $on = $current === (string) $value; @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
               class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $on ? 'bg-slate-900 !text-white' : '!text-slate-600 hover:bg-slate-100' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $on ? 'bg-white/20 text-white' : ($value === 'pending' && $count ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-500') }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-2 p-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, ажилтан, төхөөрөмж, тайлбар..." class="{{ $sel }} min-w-60 flex-1">
        <select name="type" class="{{ $sel }}" onchange="this.form.submit()">
            <option value="">Бүх төрөл</option>
            <option value="issue" @selected(request('type') === 'issue')>Эвдрэл / засвар</option>
            <option value="request" @selected(request('type') === 'request')>Төхөөрөмж захиалга</option>
        </select>
        <button type="submit" class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хайх</button>
        @if (request()->hasAny(['type', 'q']) && (filled(request('type')) || filled(request('q'))))
            <a href="{{ request()->fullUrlWithQuery(['type' => null, 'q' => null, 'page' => null]) }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm !text-slate-500 hover:bg-slate-100">Цэвэрлэх</a>
        @endif
    </div>
</form>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="w-12 px-4 py-3">№</th>
                    <th class="px-4 py-3">Огноо</th>
                    <th class="px-4 py-3">Ажилтан</th>
                    <th class="px-4 py-3">Хүсэлт</th>
                    <th class="px-4 py-3">Төлөв</th>
                    <th class="px-4 py-3"><span class="sr-only">Үйлдэл</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($requests as $r)
                @php $pending = $r->status === 'pending'; @endphp
                <tr class="align-top {{ $pending ? 'bg-amber-50/40' : '' }}">
                    <td class="px-4 py-3 font-mono text-xs text-slate-400">#{{ $requests->firstItem() + $loop->index }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $r->created_at->format('Y-m-d') }}<div>{{ $r->created_at->format('H:i') }}</div></td>
                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{{ $r->user->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        {{-- Төрөл, төхөөрөмж (холбоос), гарчиг, тайлбар --}}
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $r->type === 'issue' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $r->type === 'issue' ? 'Эвдрэл' : 'Захиалга' }}</span>
                            @if ($r->computer)
                                <a href="{{ route('computers.show', $r->computer) }}" class="font-semibold !text-slate-900 hover:!text-blue-700 hover:underline">{{ trim($r->computer->brand.' '.$r->computer->model) ?: $r->computer->name }}</a>
                                <span class="font-mono text-[11px] text-slate-400">{{ $r->computer->inventory_number }}</span>
                            @endif
                        </div>
                        @if ($r->subject)<div class="mt-1 font-medium text-slate-800">{{ $r->subject }}</div>@endif
                        <div class="mt-0.5 max-w-xl text-slate-600">«{{ $r->description }}»</div>
                        @if ($r->admin_note)
                            <div class="mt-1.5 max-w-xl rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600"><span class="font-semibold text-slate-700">Хариу:</span> {{ $r->admin_note }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[$r->status] ?? 'bg-slate-100 text-slate-600' }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $statuses[$r->status] ?? $r->status }}</span>
                        {{-- Засварт байгаа бол явц --}}
                        @if ($r->type === 'issue' && $r->computer?->status === \App\Enums\ComputerStatus::InRepair)
                            <a href="{{ route('repairs.index') }}" class="mt-1 block text-xs font-medium !text-amber-700 hover:underline">Засварт #{{ $queueNumbers[$r->computer->id] ?? '' }} · {{ $r->computer->repair_stage?->label() ?? 'Дараалалд' }}</a>
                        @endif
                        @if ($r->handler)
                            <div class="mt-1 text-[11px] text-slate-400">{{ $r->handler->name }} · {{ $r->updated_at->format('m-d H:i') }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <div class="flex items-start justify-end gap-2">
                            {{-- Эвдрэл: засварын дараалалд оруулах (засварт ороогүй, шийдвэрлэгдээгүй бол) --}}
                            @if ($r->type === 'issue' && $r->computer && in_array($r->status, ['pending', 'in_progress'], true) && $r->computer->status !== \App\Enums\ComputerStatus::InRepair)
                                <form method="POST" action="{{ route('requests.repair', $r) }}">@csrf
                                    <button class="h-8 !min-h-0 !rounded-lg !border !border-amber-300 !bg-amber-50 !px-2.5 !py-0 !text-xs !font-semibold !text-amber-800 hover:!bg-amber-100">Засварт оруулах</button>
                                </form>
                            @endif
                            {{-- Төлөв, хариу: дарахад нээгдэнэ (шийдвэрлэгдээгүйд «Шийдвэрлэх», бусдад «Засах») --}}
                            <details class="relative">
                                <summary class="flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg px-2.5 text-xs font-semibold [&::-webkit-details-marker]:hidden {{ $pending ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                    {{ $pending ? 'Шийдвэрлэх' : 'Засах' }}
                                </summary>
                                <form method="POST" action="{{ url('/requests/'.$r->id) }}" class="absolute right-0 z-10 mt-1 w-72 space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
                                    @csrf @method('PATCH')
                                    <label class="block text-xs font-medium text-slate-500">Төлөв
                                        <select name="status" class="{{ $sel }} mt-1 w-full">
                                            @foreach ($statuses as $k => $v)<option value="{{ $k }}" @selected($r->status === $k)>{{ $v }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="block text-xs font-medium text-slate-500">Ажилтанд хариу
                                        <input type="text" name="admin_note" value="{{ $r->admin_note }}" maxlength="500" placeholder="Заавал биш" class="{{ $sel }} mt-1 w-full">
                                    </label>
                                    <button type="submit" class="h-9 w-full !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хадгалах</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Хүсэлт байхгүй байна.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="border-t border-slate-100 px-4 py-3">{{ $requests->links() }}</div>
    @endif
</section>
@endsection
