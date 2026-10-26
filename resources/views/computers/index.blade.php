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

    $sel = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
@endphp

{{-- Гарчиг ба үндсэн үйлдлүүд --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="!mb-0 text-2xl font-bold">Бүх төхөөрөмж <span class="text-base font-normal text-slate-400">({{ $computers->total() }})</span></h1>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('computers.export', request()->query()) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm !text-slate-700 hover:bg-slate-50">CSV татах</a>
        <a href="{{ route('computers.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold !text-white hover:bg-blue-700">+ Шинээр бүртгэх</a>
    </div>
</div>

{{-- Төлөвийн табууд --}}
<div class="mb-3 flex gap-1 overflow-x-auto border-b border-slate-200">
    @foreach ($tabs as $value => [$label, $count, $badge])
        @php $on = $currentStatus === (string) $value; @endphp
        <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
           class="-mb-px flex shrink-0 items-center gap-2 border-b-2 bg-transparent px-3 py-2 text-sm font-medium {{ $on ? 'border-blue-600 !text-blue-700' : 'border-transparent !text-slate-500 hover:!text-slate-800' }}">
            {{ $label }}
            <span class="rounded-full px-2 py-0.5 text-xs {{ $badge }}">{{ $count }}</span>
        </a>
    @endforeach
</div>

{{-- Шүүлтүүр: сонгомогц шууд шүүнэ --}}
<form method="GET" id="filters" class="mb-4 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
    @if (filled(request('status')))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if (request('warranty') === 'expiring')<input type="hidden" name="warranty" value="expiring">@endif
    <input type="hidden" name="per_page" value="{{ $perPage }}">

    <div class="flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, дугаар, нэр, серийн, IP, хариуцагч..."
               class="{{ $sel }} min-w-0 flex-[2_1_16rem]">
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
            @foreach ($departments as $d)<option value="{{ $d }}" @selected(request('department') === $d)>{{ $d }}</option>@endforeach
        </select>
        <select name="location" class="{{ $sel }} flex-[1_1_10rem]" data-auto>
            <option value="">{{ request('department') ? 'Хэлтсийн бүх байршил' : 'Бүх байршил' }}</option>
            @foreach ($locations as $l)<option value="{{ $l }}" @selected(request('location') === $l)>{{ $l }}</option>@endforeach
        </select>
        <button class="rounded-lg !border-slate-800 !bg-slate-800 px-4 py-2 text-sm !text-white hover:!bg-slate-700">Хайх</button>
    </div>

    <details class="mt-2" @if ($advancedOpen) open @endif>
        <summary class="cursor-pointer select-none text-xs font-medium text-slate-500 hover:text-slate-800">Дэлгэрэнгүй шүүлтүүр (төрөл, бүртгэсэн админ, огноо)</summary>
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
</form>

{{-- Жагсаалт --}}
<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">{!! $sortLink('sequence_number', '№') !!}</th>
                <th class="px-4 py-3">{!! $sortLink('inventory_number', 'Дугаар') !!}</th>
                <th class="px-4">{!! $sortLink('name', 'Нэр / Загвар') !!}</th>
                <th class="px-4">Ангилал</th>
                <th class="px-4">{!! $sortLink('assigned_to', 'Хариуцагч') !!}</th>
                <th class="px-4">{!! $sortLink('department', $branches->isNotEmpty() ? 'Салбар / Хэлтэс' : 'Хэлтэс / Байршил') !!}</th>
                <th class="px-4">{!! $sortLink('status', 'Төлөв') !!}</th>
                <th class="hidden px-4 xl:table-cell">{!! $sortLink('created_at', 'Бүртгэсэн') !!}</th>
                <th class="px-4 text-right">Үйлдэл</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($computers as $c)
            @php $model = trim(($c->brand ?? '').' '.($c->model ?? '')); @endphp
            {{-- Мөр дээр дарахад дэлгэрэнгүй нээгдэнэ (холбоос, товч дээр дарахаас бусад үед) --}}
            <tr class="cursor-pointer hover:bg-slate-50" data-href="{{ route('computers.show', $c) }}">
                <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-500">{{ $globalNumbers ? '#'.$globalNumbers[$c->id] : $c->sequenceLabel() }}</td>
                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $c->inventory_number }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('computers.show', $c) }}" class="font-medium !text-blue-700 hover:underline">{{ $c->name }}</a>
                    @if ($model !== '' && $model !== $c->name)
                        <div class="text-xs text-slate-500">{{ $model }}</div>
                    @endif
                </td>
                <td class="whitespace-nowrap px-4">
                    @if ($c->category)
                        <span class="inline-flex items-center gap-1.5 text-slate-700">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c->category->icon() }}"/></svg>
                            {{ $c->category->label() }}
                        </span>
                    @else
                        <span class="text-slate-500">{{ $c->type?->label() ?? '—' }}</span>
                    @endif
                </td>
                <td class="px-4">{{ $c->assigned_to ?? '—' }}</td>
                <td class="px-4">
                    @if ($branches->isNotEmpty())
                        {{ $c->branch?->name ?? '—' }}
                        <div class="text-xs text-slate-500">{{ collect([$c->department, $c->location])->filter()->implode(' · ') }}</div>
                    @else
                        {{ $c->department ?? '—' }}
                        @if ($c->location)<div class="text-xs text-slate-500">{{ $c->location }}</div>@endif
                    @endif
                </td>
                <td class="whitespace-nowrap px-4">
                    @if (isset($queueNumbers[$c->id]))
                        <a href="{{ route('repairs.index') }}" title="Засварын дараалал" class="mr-1 rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs font-bold !text-amber-800">#{{ $queueNumbers[$c->id] }}</a>
                    @endif
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $c->status->badgeClasses() }}">{{ $c->status->label() }}</span>
                </td>
                <td class="hidden whitespace-nowrap px-4 text-xs text-slate-500 xl:table-cell">{{ $c->created_at?->format('Y-m-d') }}</td>
                <td class="whitespace-nowrap px-4 text-right">
                    <a href="{{ route('computers.edit', $c) }}" class="!text-slate-600 hover:!text-blue-600">Засах</a>
                    <form method="POST" action="{{ route('computers.destroy', $c) }}" class="ml-3 inline" onsubmit="return confirm('«{{ addslashes($c->name) }}»-г устгах уу? (Устгасан хэсэгт хадгалагдана, сэргээж болно)')">
                        @csrf @method('DELETE')
                        <button class="!border-0 !bg-transparent !p-0 !text-sm !font-normal text-rose-600 hover:underline">Устгах</button>
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

<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <div>{{ $computers->links() }}</div>
    <label class="flex items-center gap-2 text-xs text-slate-500">
        Нэг хуудсанд
        <select class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs"
                onchange="location = @js(request()->fullUrlWithQuery(['per_page' => '__N__', 'page' => null])).replace('__N__', this.value)">
            @foreach ([15, 30, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
        </select>
    </label>
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
