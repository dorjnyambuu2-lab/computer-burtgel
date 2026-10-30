@extends('layouts.app')

@section('title', 'Төхөөрөмж олгох')

@section('content')
@php
    // Ангиллын дараалал: enum-ийн дарааллаар, "Ангилаагүй" хамгийн сүүлд
    $order = array_merge(array_map(fn ($c) => $c->value, \App\Enums\EquipmentCategory::cases()), ['none']);
    $labelOf = fn ($key) => \App\Enums\EquipmentCategory::tryFrom($key)?->label() ?? 'Ангилаагүй';
    $iconOf = fn ($key) => \App\Enums\EquipmentCategory::tryFrom($key)?->icon() ?? 'M4 4h16v16H4zM9 9h6v6H9z';
    $totalAvailable = $available->sum(fn ($group) => $group->count());
    $picked = array_map('intval', (array) old('computer_ids', []));
    $field = 'h-10 w-full !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $initials = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name), 0, 2));
    $searchIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <div>
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Төхөөрөмж олгох</h1>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- 1. Ажилтан: хайж сонгоно (сонгосон бол товч мөр, «Солих» дарахад жагсаалт нээгдэнэ) --}}
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
            <h2 class="!mb-0 text-sm font-semibold text-slate-800">Ажилтан</h2>
        </div>

        @if ($selected)
            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-blue-700">{{ $initials($selected->name) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-slate-900">{{ trim($selected->surname.' '.$selected->name) }}</div>
                    <div class="text-xs text-slate-500">{{ collect([$selected->department, $selected->position, $selected->username])->filter()->implode(' · ') ?: '—' }}</div>
                </div>
                <button type="button" id="change-employee"
                        class="h-9 !min-h-0 !rounded-lg !border !border-slate-200 !bg-white !px-3 !py-0 !text-sm !font-medium !text-slate-700 hover:!bg-slate-50">Солих</button>
            </div>
        @endif

        <div id="employee-picker" class="{{ $selected ? 'hidden border-t border-slate-100' : '' }} space-y-3 px-5 py-4">
            <div class="flex flex-wrap gap-2">
                <div class="relative min-w-0 flex-[2_1_14rem]">
                    {!! $searchIcon !!}
                    <input type="search" id="emp-q" placeholder="Нэр, нэвтрэх нэр, алба..." autocomplete="off" class="{{ $field }} !pl-9">
                </div>
                <select id="emp-dept" aria-label="Алба хэлтэс" class="{{ $field }} flex-[1_1_10rem]">
                    <option value="">Бүх алба хэлтэс</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d }}" @selected($department === $d)>{{ $d }}</option>
                    @endforeach
                    <option value="none" @selected($department === 'none')>Хэлтэсгүй</option>
                </select>
                {{-- Жагсаалтад байхгүй шинэ ажилтан: бүртгээд энэ хуудас руу сонгогдсон байдлаар буцна --}}
                @if (array_key_exists('employee', auth()->user()->assignableRoles()))
                    <a href="{{ route('users.create', ['role' => 'employee', 'return' => 'bulk-assign']) }}"
                       class="inline-flex h-10 shrink-0 items-center rounded-lg border border-blue-600 bg-white px-4 text-sm font-semibold !text-blue-700 hover:bg-blue-50">+ Шинэ ажилтан</a>
                @endif
            </div>

            <ul id="emp-list" class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                @foreach ($all as $employee)
                    <li data-emp data-dept="{{ $employee->department ?: 'none' }}"
                        data-search="{{ mb_strtolower(implode(' ', [$employee->surname, $employee->name, $employee->username, $employee->department, $employee->position])) }}">
                        <a href="{{ route('computers.bulk-assign', ['user_id' => $employee->id]) }}"
                           class="flex items-center gap-3 px-3 py-2.5 !text-slate-700 transition hover:bg-blue-50 {{ $selected?->id === $employee->id ? 'bg-blue-50' : '' }}">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">{{ $initials($employee->name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-900">{{ trim($employee->surname.' '.$employee->name) }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ collect([$employee->department, $employee->position, $employee->username])->filter()->implode(' · ') ?: '—' }}</span>
                            </span>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $employee->computers_count ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400' }}"
                                  title="Одоо оноогдсон төхөөрөмж">{{ $employee->computers_count }} төхөөрөмж</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p id="emp-empty" class="hidden py-4 text-center text-sm text-slate-400">Тохирох ажилтан олдсонгүй.</p>
        </div>
    </section>

    @if ($selected)
        {{-- Ажилтны одоогийн төхөөрөмжүүд: товч жагсаалт --}}
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <h2 class="!mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Одоо байгаа <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 normal-case tracking-normal text-slate-500">{{ $current->count() }}</span>
            </h2>
            @if ($current->isEmpty())
                <p class="text-sm text-slate-400">Одоогоор төхөөрөмж оноогдоогүй.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($current as $item)
                        @php $key = $item->category?->value ?? 'none'; @endphp
                        <a href="{{ route('computers.show', $item) }}" title="{{ $item->inventory_number }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs !text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                            {{ $item->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 2. Олгох төхөөрөмж сонгох --}}
        <form method="POST" action="{{ route('computers.bulk-assign.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selected->id }}">

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                    <h2 class="!mb-0 text-sm font-semibold text-slate-800">Олгох төхөөрөмж</h2>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">чөлөөтэй {{ $totalAvailable }}</span>
                    @if ($totalAvailable > 0)
                        <div class="relative ml-auto w-full sm:w-64">
                            {!! $searchIcon !!}
                            <input type="search" id="dev-q" placeholder="Нэр, дугаар, серийн..." autocomplete="off" class="{{ $field }} !pl-9">
                        </div>
                    @endif
                </div>

                <div class="space-y-5 px-5 py-4">
                @if ($totalAvailable === 0)
                    <p class="text-sm text-slate-500">
                        Чөлөөтэй төхөөрөмж байхгүй байна. Эхлээд
                        <a href="{{ route('computers.create') }}" class="font-medium text-blue-600 hover:underline">шинээр бүртгэнэ</a>
                        (процессор, дэлгэц, mouse ... нэг дор бүртгэж болно).
                    </p>
                @else
                    @foreach ($order as $key)
                        @continue(! $available->has($key))

                        <div data-group>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                                {{ $labelOf($key) }}
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $available[$key]->count() }}</span>
                                <button type="button" data-select-all class="ml-auto !min-h-0 !border-0 !bg-transparent !p-0 !text-xs font-medium !text-blue-600 hover:underline">Бүгдийг сонгох</button>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($available[$key] as $computer)
                                    @php $kit = $kitSizes[$computer->id] ?? 0; @endphp
                                    <label data-device
                                           data-search="{{ mb_strtolower(implode(' ', [$computer->name, $computer->inventory_number, $computer->brand, $computer->model, $computer->serial_number])) }}"
                                           class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                        <input type="checkbox" name="computer_ids[]" value="{{ $computer->id }}"
                                               data-id="{{ $computer->id }}" @if ($computer->kit_parent_id) data-kit="{{ $computer->kit_parent_id }}" @endif
                                               class="mt-0.5 h-4 w-4 shrink-0" @checked(in_array($computer->id, $picked, true))>
                                        <span class="min-w-0 flex-1 text-sm">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-medium text-slate-900">{{ $computer->name }}</span>
                                                @if ($kit)
                                                    <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700" title="Сонгоход иж бүрдлийн хэрэгслүүд хамт сонгогдоно">+{{ $kit }} иж бүрдэл</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block truncate text-xs text-slate-500">
                                                {{ $computer->inventory_number }}
                                                @if (trim($computer->brand.' '.$computer->model) !== '') · {{ trim($computer->brand.' '.$computer->model) }}@endif
                                                @if ($computer->serial_number) · S/N {{ $computer->serial_number }}@endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p id="dev-empty" class="hidden py-4 text-center text-sm text-slate-400">Хайлтад тохирох төхөөрөмж олдсонгүй.</p>

                    <div>
                        <label for="note" class="mb-1 block text-xs font-medium text-slate-500">Тайлбар (заавал биш)</label>
                        <input type="text" name="note" id="note" maxlength="1000" value="{{ old('note') }}"
                               placeholder="Жишээ: Шинэ ажилтны багц" class="{{ $field }}">
                    </div>
                @endif
                </div>
            </section>

            @if ($totalAvailable > 0)
                <div class="sticky bottom-0 -mx-4 flex items-center justify-between gap-3 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
                    <span class="text-sm text-slate-500">
                        Сонгосон: <b id="picked-count" class="text-slate-900">0</b>
                        <button type="button" id="clear-picked" class="ml-2 hidden !min-h-0 !border-0 !bg-transparent !p-0 !text-xs !font-medium !text-slate-500 hover:!text-slate-800 hover:underline">Цэвэрлэх</button>
                        {{-- Иж бүрдлийг хэсэгчлэн олгоход: сонгоогүй хэрэгсэл иж бүрдлээс салж агуулахад үлдэнэ --}}
                        <span id="kit-split" class="mt-0.5 hidden text-xs text-amber-700"></span>
                    </span>
                    <button type="submit" id="assign-btn"
                            class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-6 !py-0 text-sm font-semibold !text-white shadow-sm hover:!bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $selected->name }}-д олгох
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>

<script>
(function () {
    // ── Ажилтан: хайх, албаар шүүх (хуудас дахин ачаалахгүй) ──
    const picker = document.getElementById('employee-picker');
    const change = document.getElementById('change-employee');
    if (change) change.addEventListener('click', function () {
        picker.classList.toggle('hidden');
        if (!picker.classList.contains('hidden')) document.getElementById('emp-q').focus();
    });
    const empQ = document.getElementById('emp-q');
    const empDept = document.getElementById('emp-dept');
    function filterEmployees() {
        const q = empQ.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('[data-emp]').forEach(function (li) {
            const ok = (!q || li.dataset.search.indexOf(q) !== -1) && (!empDept.value || li.dataset.dept === empDept.value);
            li.classList.toggle('hidden', !ok);
            if (ok) shown++;
        });
        document.getElementById('emp-empty').classList.toggle('hidden', shown > 0);
    }
    if (empQ) {
        empQ.addEventListener('input', filterEmployees);
        empDept.addEventListener('change', filterEmployees);
        filterEmployees();
        if (!change) empQ.focus();
    }

    // ── Төхөөрөмж: хайлт, иж бүрдэл, тоолуур ──
    const boxes = Array.from(document.querySelectorAll('input[name="computer_ids[]"]'));
    const counter = document.getElementById('picked-count');
    const btn = document.getElementById('assign-btn');
    const clear = document.getElementById('clear-picked');
    if (!counter || !btn) return;

    const splitNote = document.getElementById('kit-split');
    function update() {
        const n = boxes.filter(function (b) { return b.checked; }).length;
        counter.textContent = n;
        btn.disabled = n === 0;
        clear.classList.toggle('hidden', n === 0);

        // Иж бүрдэл хэсэгчлэн: үндсэн нь сонгогдсон ч хэрэгсэл нь үгүй (эсвэл эсрэгээр) бол салгагдана
        const split = boxes.filter(function (b) {
            if (!b.checked) return false;
            const main = b.dataset.kit ? boxes.find(function (x) { return x.dataset.id === b.dataset.kit; }) : null;
            return (main && !main.checked)
                || boxes.some(function (x) { return x.dataset.kit === b.dataset.id && !x.checked; });
        }).length;
        splitNote.textContent = split ? 'Сонгоогүй иж бүрдлийн хэрэгсэл иж бүрдлээс салж, агуулахад тусдаа үлдэнэ.' : '';
        splitNote.classList.toggle('hidden', !split);
        splitNote.classList.toggle('block', !!split);
    }

    // Үндсэн компьютерийг сонгох / болиход иж бүрдлийн хэрэгслүүд хамт
    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            boxes.filter(function (x) { return x.dataset.kit === b.dataset.id; }).forEach(function (x) { x.checked = b.checked; });
            update();
        });
    });

    const devQ = document.getElementById('dev-q');
    devQ.addEventListener('input', function () {
        const q = devQ.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('[data-group]').forEach(function (group) {
            let groupShown = 0;
            group.querySelectorAll('[data-device]').forEach(function (card) {
                const ok = !q || card.dataset.search.indexOf(q) !== -1;
                card.classList.toggle('hidden', !ok);
                if (ok) groupShown++;
            });
            group.classList.toggle('hidden', groupShown === 0);
            shown += groupShown;
        });
        document.getElementById('dev-empty').classList.toggle('hidden', shown > 0);
    });

    // Ангилал бүрийн «Бүгдийг сонгох» (харагдаж буйг): бүгд сонгогдсон бол сонголтыг арилгана
    document.querySelectorAll('[data-select-all]').forEach(function (link) {
        link.addEventListener('click', function () {
            const group = Array.from(link.closest('[data-group]').querySelectorAll('[data-device]:not(.hidden) input[name="computer_ids[]"]'));
            const allChecked = group.every(function (b) { return b.checked; });
            group.forEach(function (b) { b.checked = !allChecked; b.dispatchEvent(new Event('change')); });
            update();
        });
    });

    clear.addEventListener('click', function () {
        boxes.forEach(function (b) { b.checked = false; });
        update();
    });

    update();
})();
</script>
@endsection
