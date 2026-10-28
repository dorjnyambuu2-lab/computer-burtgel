@extends('layouts.app')
@section('title', 'Бүх төхөөрөмж')
@section('content')
@php
    $sort = request('sort', 'id');
    $dir = request('dir') === 'asc' ? 'asc' : 'desc';
    // Баганын толгой дарахад тухайн баганаар эрэмбэлж, дахин дарахад эсрэг чиглэлд
    $sortLink = function (string $key, string $label) use ($sort, $dir) {
        $next = $sort === $key && $dir === 'asc' ? 'desc' : 'asc';
        $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);

        return '<a href="'.e($url).'" class="!text-slate-500 hover:!text-slate-900">'.e($label).$arrow.'</a>';
    };

    // Идэвхтэй шүүлтүүрүүд: «Ангилал: Дэлгэц ×» гэж харуулж, тус бүрийг нь арилгаж болно
    $adminNames = $admins->pluck('name', 'id');
    $chips = array_filter([
        'q' => filled(request('q')) ? 'Хайлт: «'.request('q').'»' : null,
        'branch' => isset($branches[request('branch')]) ? 'Салбар: '.$branches[request('branch')] : null,
        'category' => $categories[request('category')] ?? null,
        'type' => isset($types[request('type')]) ? 'Төрөл: '.$types[request('type')] : null,
        'department' => filled(request('department')) ? 'Хэлтэс: '.request('department') : null,
        'location' => filled(request('location')) ? 'Байршил: '.request('location') : null,
        'created_by' => isset($adminNames[request('created_by')]) ? 'Бүртгэсэн: '.$adminNames[request('created_by')] : null,
        'date_from' => filled(request('date_from')) ? request('date_from').'-аас' : null,
        'date_to' => filled(request('date_to')) ? request('date_to').' хүртэл' : null,
        'warranty' => request('warranty') === 'expiring' ? 'Баталгаа 60 хоногт дуусах' : null,
    ]);
    $advancedOpen = collect(['type', 'created_by', 'date_from', 'date_to'])->contains(fn ($k) => filled(request($k)));

    $allCount = array_sum($statusCounts);
    $currentStatus = (string) request('status');
    $tabs = ['' => ['Бүгд', $allCount, 'bg-slate-100 text-slate-600']];
    foreach (\App\Enums\ComputerStatus::cases() as $s) {
        $tabs[$s->value] = [$s->label(), (int) ($statusCounts[$s->value] ?? 0), $s->badgeClasses()];
    }

    // Шүүлтүүрийн бүх удирдлага ижил өндөртэй (h-10)
    $sel = 'h-10 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
@endphp

{{-- Гарчиг ба үндсэн үйлдлүүд --}}
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Бүх төхөөрөмж</h1>
        <p class="mt-1 text-sm text-slate-500"><b class="font-semibold text-slate-700">{{ $deviceCount }}</b> төхөөрөмж <span class="text-slate-400">· mouse, keyboard тоологдохгүй</span></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('computers.export', request()->query()) }}" class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium !text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
            CSV татах
        </a>
        <a href="{{ route('computers.create') }}" class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-semibold !text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Шинээр бүртгэх
        </a>
    </div>
</div>

{{-- Төлөвийн табууд + шүүлтүүр: нэг карт. Шүүлтүүр сонгомогц шууд шүүнэ --}}
<form method="GET" id="filters" class="mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
    @if (filled(request('status')))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if (request('warranty') === 'expiring')<input type="hidden" name="warranty" value="expiring">@endif
    <input type="hidden" name="per_page" value="{{ $perPage }}">

    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 p-2">
        @foreach ($tabs as $value => [$label, $count, $badge])
            @php $on = $currentStatus === (string) $value; @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
               class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $on ? 'bg-slate-900 !text-white' : '!text-slate-600 hover:bg-slate-100' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $on ? 'bg-white/20 text-white' : $badge }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <div class="p-3">
    <div class="flex flex-wrap gap-2">
        <div class="relative min-w-0 flex-[2_1_16rem]">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, дугаар, нэр, серийн, IP, хариуцагч..."
                   class="{{ $sel }} w-full !pl-9">
        </div>
        @if ($branches->isNotEmpty())
            <select name="branch" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
                <option value="">Бүх салбар</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) request('branch') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        @endif
        <select name="category" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
            <option value="">Бүх ангилал</option>
            @foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="department" class="{{ $sel }} flex-[1_1_10rem]" data-auto onchange="this.form.location.value=''">
            <option value="">Бүх алба хэлтэс</option>
            @foreach ($departments as $d)<option value="{{ $d }}" @selected(request('department') === $d)>{{ $d }} ({{ $departmentCounts[$d] ?? 0 }})</option>@endforeach
        </select>
        <select name="location" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
            <option value="">{{ request('department') ? 'Хэлтсийн бүх байршил' : 'Бүх байршил' }}</option>
            @foreach ($locations as $l)<option value="{{ $l }}" @selected(request('location') === $l)>{{ $l }}</option>@endforeach
        </select>
        <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хайх</button>
    </div>

    <details class="group mt-2" @if ($advancedOpen) open @endif>
        <summary class="inline-flex cursor-pointer select-none list-none items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800 [&::-webkit-details-marker]:hidden">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="transition group-open:rotate-90" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            Дэлгэрэнгүй шүүлтүүр (төрөл, бүртгэсэн админ, огноо)
        </summary>
        <div class="mt-2 flex flex-wrap gap-2">
            <select name="type" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
                <option value="">Бүх төрөл</option>
                @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
            </select>
            <select name="created_by" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
                <option value="">Бүртгэсэн: бүх админ</option>
                @foreach ($admins as $a)<option value="{{ $a->id }}" @selected((string) request('created_by') === (string) $a->id)>{{ $a->name }}</option>@endforeach
            </select>
            <label class="flex flex-[2_1_18rem] items-center gap-1 text-xs text-slate-500">
                Бүртгэсэн огноо
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="{{ $sel }} min-w-0 flex-1" data-auto>
                <span>–</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="{{ $sel }} min-w-0 flex-1" data-auto>
            </label>
        </div>
    </details>

    @if ($chips)
        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs">
            @foreach ($chips as $key => $text)
                <a href="{{ request()->fullUrlWithQuery([$key => null, 'page' => null]) }}" title="Арилгах"
                   class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 font-medium !text-blue-700 hover:bg-blue-100">
                    {{ $text }} <span class="text-blue-400">✕</span>
                </a>
            @endforeach
            <a href="{{ route('computers.index', array_filter(['sort' => request('sort'), 'dir' => request('dir'), 'status' => request('status')])) }}"
               class="ml-1 !text-slate-500 hover:!text-slate-800 hover:underline">Бүгдийг цэвэрлэх</a>
        </div>
    @endif
    </div>
</form>

{{-- Жагсаалт --}}
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">{!! $sortLink('sequence_number', '№') !!}</th>
                <th class="px-4 py-3">{!! $sortLink('inventory_number', 'Дугаар') !!}</th>
                <th class="px-4 py-3">{!! $sortLink('name', 'Нэр / Загвар') !!}</th>
                <th class="px-4 py-3">Ангилал</th>
                <th class="px-4 py-3">{!! $sortLink('assigned_to', 'Хариуцагч') !!}</th>
                <th class="px-4 py-3">{!! $sortLink('department', $branches->isNotEmpty() ? 'Салбар / Хэлтэс' : 'Хэлтэс / Байршил') !!}</th>
                <th class="px-4 py-3">{!! $sortLink('status', 'Төлөв') !!}</th>
                <th class="hidden px-4 py-3 xl:table-cell">{!! $sortLink('created_at', 'Бүртгэсэн') !!}</th>
                <th class="px-4 py-3 text-right"><span class="sr-only">Үйлдэл</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($computers as $c)
            @php $model = trim(($c->brand ?? '').' '.($c->model ?? '')); @endphp
            {{-- Мөр дээр дарахад дэлгэрэнгүй нээгдэнэ (холбоос, товч дээр дарахаас бусад үед) --}}
            <tr class="cursor-pointer transition hover:bg-slate-50" data-href="{{ route('computers.show', $c) }}">
                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ isset($globalNumbers[$c->kit_parent_id ?? $c->id]) ? '#'.$globalNumbers[$c->kit_parent_id ?? $c->id] : $c->sequenceLabel() }}</td>
                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{{ $c->inventory_number }}</span></td>
                <td class="px-4 py-3">
                    <a href="{{ route('computers.show', $c) }}" class="font-semibold !text-slate-900 hover:!text-blue-700">{{ $c->name }}</a>
                    @if ($model !== '' && $model !== $c->name)
                        <div class="text-xs text-slate-500">{{ $model }}</div>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if ($c->category)
                        <span class="inline-flex items-center gap-1.5 text-slate-700">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c->category->icon() }}"/></svg>
                            {{ $c->category->label() }}
                        </span>
                    @else
                        <span class="text-slate-500">{{ $c->type?->label() ?? '—' }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 {{ $c->assigned_to ? 'text-slate-700' : 'text-slate-300' }}">{{ $c->assigned_to ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-700">
                    @if ($branches->isNotEmpty())
                        {{ $c->branch?->name ?? '—' }}
                        <div class="text-xs text-slate-500">{{ collect([$c->department, $c->location])->filter()->implode(' · ') }}</div>
                    @else
                        {{ $c->department ?? '—' }}
                        @if ($c->location)<div class="text-xs text-slate-500">{{ $c->location }}</div>@endif
                    @endif
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    @if (isset($queueNumbers[$c->id]))
                        <a href="{{ route('repairs.index') }}" title="Засварын дараалал" class="mr-1 rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs font-bold !text-amber-800">#{{ $queueNumbers[$c->id] }}</a>
                    @endif
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $c->status->badgeClasses() }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $c->status->label() }}</span>
                </td>
                <td class="hidden whitespace-nowrap px-4 py-3 text-xs text-slate-500 xl:table-cell">{{ $c->created_at?->format('Y-m-d') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    {{-- Засах, устгах: икон товч, hover-т өнгө, тайлбар (title) --}}
                    <a href="{{ route('computers.edit', $c) }}" title="Засах" aria-label="Засах"
                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg !text-slate-500 transition hover:bg-blue-50 hover:!text-blue-600">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('computers.destroy', $c) }}" class="ml-1 inline" onsubmit="return confirm(@js('«'.$c->name.'»-г устгах уу?'.($c->kit_items_count ? ' Иж бүрдлийн '.$c->kit_items_count.' дагалдах хэрэгсэл (дэлгэц, mouse ...) хамт устгагдана.' : '').' (Устгасан хэсэгт хадгалагдана, сэргээж болно)'))">
                        @csrf @method('DELETE')
                        <button title="Устгах" aria-label="Устгах"
                                class="inline-flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-500 transition hover:!bg-rose-50 hover:!text-rose-600">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                        </button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                    @if ($chips || filled(request('status')))
                        Шүүлтүүрт тохирох төхөөрөмж олдсонгүй.
                        <a href="{{ route('computers.index') }}" class="ml-1 !text-blue-600 hover:underline">Шүүлтүүр цэвэрлэх</a>
                    @else
                        Одоогоор бүртгэлтэй төхөөрөмж алга.
                        <a href="{{ route('computers.create') }}" class="ml-1 !text-blue-600 hover:underline">Эхний төхөөрөмжөө бүртгэх</a>
                    @endif
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    {{-- Хуудаслалт: картын доод хэсэгт --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3">
        <div>{{ $computers->links() }}</div>
        <label class="flex items-center gap-2 text-xs text-slate-500">
            Нэг хуудсанд
            <select class="!min-h-0 !rounded-lg border !border-slate-200 bg-white !px-2 !py-1 text-xs"
                    onchange="location = @js(request()->fullUrlWithQuery(['per_page' => '__N__', 'page' => null])).replace('__N__', this.value)">
                @foreach ([15, 30, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
            </select>
        </label>
    </div>
</div>

<script>
// Шүүлтүүр сонгомогц шууд шүүнэ
document.querySelectorAll('#filters [data-auto]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
});
// Мөр дээр дарахад дэлгэрэнгүй хуудас руу
document.querySelectorAll('tr[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a, button, form, input, select')) return;
        location = row.dataset.href;
    });
});
</script>
@endsection
