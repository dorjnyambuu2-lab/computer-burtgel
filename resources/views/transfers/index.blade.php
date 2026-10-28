@extends('layouts.app')
@section('title', 'Төхөөрөмж шилжүүлэх')
@section('content')
@php
    $inp = 'rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm';
    $field = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

<div class="mx-auto max-w-6xl space-y-4">
    <div>
        <h1 class="!mb-0 text-xl font-bold">Төхөөрөмж шилжүүлэх</h1>    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Шилжүүлэх: төхөөрөмж → ажилтан (Супер админд мөн админ, алба хэлтэс, агуулах) --}}
    <form method="POST" action="{{ route('transfers.store') }}" class="rounded-2xl border-2 border-blue-200 bg-white p-5 shadow-sm">
        @csrf
        <h2 class="mb-3 text-base font-bold">Шинэ шилжүүлэг</h2>
        @if ($devices->isEmpty())
            <p class="text-sm text-slate-500">Ажилтанд оноогдсон төхөөрөмж алга. Төхөөрөмжийг эхлээд <a href="{{ route('computers.bulk-assign') }}" class="font-semibold !text-blue-600 hover:underline">«Төхөөрөмж олгох»</a>-оор олгоно.</p>
        @else
            @php
                $destination = old('destination', 'user');
                // Иж бүрдэл (үндсэн + дагалдах, ижил эзэмшигч) нэг сонголт болж, бүхэлдээ шилжинэ
                $selectedId = (string) old('computer_id', request('computer'));
                $sets = $devices->groupBy(fn ($d) => ($d->kit_parent_id ?: $d->id).'|'.$d->assigned_user_id)
                    ->map(fn ($g) => $g->sortBy(fn ($d) => [$d->kit_parent_id ? 1 : 0, $d->kit_index ?? 0, $d->id])->values());
                // Сонгох цонхны хүснэгтийн мөрүүд: иж бүрдэл бүр нэг мөр (үндсэн төхөөрөмжөөр)
                $deviceRows = $sets->map(function ($set) {
                    $d = $set->first();

                    return [
                        'id' => $d->id,
                        'ids' => $set->pluck('id')->map(fn ($id) => (string) $id)->all(),
                        'code' => $d->inventory_number,
                        'name' => $d->name,
                        'category' => $d->category?->label(),
                        'place' => $d->place,
                        'department' => $d->department,
                        'location' => $d->location,
                        'holder' => $d->assigned_user_id ? (string) $d->assigned_user_id : '',
                        'branch' => $d->branch_id ? (string) $d->branch_id : '',
                        'branchName' => $d->branch?->name,
                        'kit' => $set->count(),
                        'items' => $set->count() > 1 ? $set->map(fn ($i) => $i->inventory_number.' '.$i->name)->implode(', ') : '',
                    ];
                })->sortBy(['place', 'code'])->values();
                $filterValues = fn (string $key) => $deviceRows->pluck($key)->filter()->unique()->sort()->values();
            @endphp

            {{-- Супер админ: хаашаа шилжүүлэх --}}
            @if ($destinations)
                <div class="mb-4 flex flex-wrap gap-2" role="radiogroup" aria-label="Хаашаа шилжүүлэх">
                    @foreach ($destinations as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="destination" value="{{ $key }}" class="peer sr-only" @checked($destination === $key)>
                            <span class="block rounded-full border border-slate-300 px-4 py-1.5 text-sm font-medium text-slate-600 peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-[2fr_1.5fr_2fr_auto] md:items-end">
                {{-- Төхөөрөмж: дарахад хайлт, шүүлтүүртэй хүснэгт бүхий цонх нээгдэнэ (доорх #dlg-device) --}}
                <div class="text-sm font-medium text-slate-700">Төхөөрөмж <span class="text-rose-500">*</span>
                    <input type="hidden" name="computer_id" id="transfer-device" value="">
                    <button type="button" id="transfer-device-pick"
                            class="mt-1 flex w-full !min-h-0 items-center gap-2 !rounded-xl !border !border-slate-300 !bg-white !px-3 !py-2.5 text-left !text-sm !font-normal shadow-sm transition hover:!border-blue-400 focus:!border-blue-500">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="shrink-0 text-slate-400" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                        <span id="transfer-device-label" class="min-w-0 flex-1 truncate text-slate-400">Төхөөрөмж сонгох…</span>
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-slate-400" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </div>

                {{-- Хүлээн авагч: чиглэлээс хамаарч нэг нь харагдана --}}
                <div>
                    {{-- Супер админд админ, ажилтан тусдаа бүлгээр; Админд зөвхөн ажилтан --}}
                    <label data-dest="user" class="block text-sm font-medium text-slate-700">Шилжүүлэн авах {{ $destinations ? 'хэрэглэгч' : 'ажилтан' }} <span class="text-rose-500">*</span>
                        <select name="to_user_id" class="transfer-user {{ $field }}">
                            <option value="">— Сонгох —</option>
                            @if ($destinations)
                                <optgroup label="Админ">
                                    @foreach ($admins as $a)
                                        <option value="{{ $a->id }}" data-branch="{{ $a->branch_id }}" @selected((string) old('to_user_id') === (string) $a->id)>{{ $a->name }} (админ){{ $a->branch ? ' · '.$a->branch->name : '' }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Ажилтан">
                            @endif
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" data-branch="{{ $e->branch_id }}" @selected((string) old('to_user_id') === (string) $e->id)>{{ $e->name }}</option>
                            @endforeach
                            @if ($destinations)
                                </optgroup>
                            @endif
                        </select>
                    </label>
                    @if ($destinations)
                        <label data-dest="department" class="block text-sm font-medium text-slate-700">Алба хэлтэс <span class="text-rose-500">*</span>
                            <input type="text" name="department" list="transfer-departments" value="{{ old('department') }}" maxlength="255" placeholder="Сонгох эсвэл бичих" class="{{ $field }}">
                            <datalist id="transfer-departments">
                                @foreach ($departments as $dep)<option value="{{ $dep }}"></option>@endforeach
                            </datalist>
                        </label>
                        <div data-dest="storage" class="text-sm font-medium text-slate-700">Хаашаа
                            <div class="mt-1 rounded-xl border border-dashed border-sky-300 bg-sky-50 px-3 py-2.5 text-sm text-sky-800">Агуулах</div>
                        </div>
                    @endif
                </div>

                <label class="block text-sm font-medium text-slate-700">Шалтгаан
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="500" placeholder="Жишээ: өөр хэлтэс рүү шилжсэн" class="{{ $field }}">
                </label>
                <button class="rounded-xl !border-blue-600 !bg-blue-600 px-5 py-2.5 text-sm font-semibold !text-white hover:!bg-blue-700">Шилжүүлэх</button>
            </div>
            <p id="transfer-from" class="mt-2 hidden text-xs text-slate-500"></p>

            {{-- Төхөөрөмж сонгох цонх: хайлт, шүүлтүүр (алба, байршил, иж бүрдэл, салбар), хүснэгт. Мөр дээр дарж сонгоно --}}
            @php $sel = 'h-10 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700'; @endphp
            <dialog id="dlg-device" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(64rem,96vw);border:0;">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h3 class="!mb-0 text-lg font-bold text-slate-900">Төхөөрөмж сонгох</h3>
                    <button type="button" data-close title="Хаах" aria-label="Хаах"
                            class="flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-400 hover:!bg-slate-100 hover:!text-slate-700">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                    <div class="relative min-w-0 flex-[2_1_14rem]">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                        <input type="search" id="dev-q" placeholder="Хайх: дугаар, нэр, хариуцагч..." autocomplete="off" class="{{ $sel }} w-full !pl-9">
                    </div>
                    <select id="dev-dept" class="{{ $sel }} flex-[1_1_9rem]" aria-label="Алба">
                        <option value="">Бүх алба</option>
                        @foreach ($filterValues('department') as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                    <select id="dev-loc" class="{{ $sel }} flex-[1_1_9rem]" aria-label="Байршил">
                        <option value="">Бүх байршил</option>
                        @foreach ($filterValues('location') as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
                    </select>
                    <select id="dev-kit" class="{{ $sel }} flex-[1_1_9rem]" aria-label="Иж бүрдэл">
                        <option value="">Иж бүрдэл: бүгд</option>
                        <option value="kit">Зөвхөн иж бүрдэл</option>
                        <option value="single">Ганц төхөөрөмж</option>
                    </select>
                    @if ($showBranch)
                        <select id="dev-branch" class="{{ $sel }} flex-[1_1_9rem]" aria-label="Салбар">
                            <option value="">Бүх салбар</option>
                            @foreach ($deviceRows->pluck('branchName', 'branch')->filter()->sort() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    @endif
                </div>
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-white text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Код</th>
                                <th class="px-4 py-2.5">Нэр</th>
                                <th class="px-4 py-2.5">Хариуцагч / байдал</th>
                                <th class="px-4 py-2.5">Алба · Байршил</th>
                                @if ($showBranch)<th class="px-4 py-2.5">Салбар</th>@endif
                                <th class="px-4 py-2.5">Иж бүрдэл</th>
                            </tr>
                        </thead>
                        <tbody id="dev-rows" class="divide-y divide-slate-100"></tbody>
                    </table>
                    <p id="dev-empty" class="hidden px-4 py-10 text-center text-sm text-slate-400">Шүүлтүүрт тохирох төхөөрөмж олдсонгүй.</p>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    <span id="dev-count"></span>
                    <span>Мөр дээр дарж сонгоно · Esc хаана</span>
                </div>
            </dialog>

            <script>
            // Төхөөрөмж сонгох цонх + сонгосон чиглэлийн талбарыг л харуулна (бусдыг идэвхгүй болгож илгээхгүй).
            // Хэрэглэгчийн жагсаалтаас одоогийн эзэмшигч, өөр салбарынхныг хасна. Иж бүрдэл бүхэлдээ шилжинэ.
            (function () {
                const rows = @json($deviceRows);
                const showBranch = @json($showBranch);
                const input = document.getElementById('transfer-device');
                const label = document.getElementById('transfer-device-label');
                const pick = document.getElementById('transfer-device-pick');
                const dialog = document.getElementById('dlg-device');
                const tbody = document.getElementById('dev-rows');
                const from = document.getElementById('transfer-from');
                const radios = document.querySelectorAll('input[name="destination"]');
                const panels = document.querySelectorAll('[data-dest]');
                const filters = ['dev-q', 'dev-dept', 'dev-loc', 'dev-kit', 'dev-branch'].map(function (id) { return document.getElementById(id); }).filter(Boolean);
                let selected = null;

                function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
                function value(id) { const el = document.getElementById(id); return el ? el.value : ''; }

                function render() {
                    const q = value('dev-q').trim().toLowerCase();
                    const list = rows.filter(function (r) {
                        return (!q || [r.code, r.name, r.place, r.items, r.category].join(' ').toLowerCase().indexOf(q) !== -1)
                            && (!value('dev-dept') || r.department === value('dev-dept'))
                            && (!value('dev-loc') || r.location === value('dev-loc'))
                            && (!value('dev-kit') || (value('dev-kit') === 'kit') === (r.kit > 1))
                            && (!value('dev-branch') || r.branch === value('dev-branch'));
                    });
                    tbody.innerHTML = list.map(function (r) {
                        const on = selected && selected.id === r.id;
                        return '<tr data-id="' + r.id + '" class="cursor-pointer transition ' + (on ? 'bg-blue-50' : 'hover:bg-slate-50') + '">'
                            + '<td class="whitespace-nowrap px-4 py-2.5"><span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">' + esc(r.code) + '</span></td>'
                            + '<td class="px-4 py-2.5"><div class="font-semibold text-slate-900">' + esc(r.name) + '</div>' + (r.category ? '<div class="text-xs text-slate-500">' + esc(r.category) + '</div>' : '') + '</td>'
                            + '<td class="px-4 py-2.5 text-slate-700">' + esc(r.place) + '</td>'
                            + '<td class="px-4 py-2.5 text-slate-600">' + esc([r.department, r.location].filter(Boolean).join(' · ') || '—') + '</td>'
                            + (showBranch ? '<td class="px-4 py-2.5 text-slate-600">' + esc(r.branchName || '—') + '</td>' : '')
                            + '<td class="px-4 py-2.5">' + (r.kit > 1
                                ? '<span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700" title="' + esc(r.items) + '">' + r.kit + ' төхөөрөмж</span>'
                                : '<span class="text-xs text-slate-400">—</span>') + '</td>'
                            + '</tr>';
                    }).join('');
                    document.getElementById('dev-empty').classList.toggle('hidden', list.length > 0);
                    document.getElementById('dev-count').textContent = list.length + ' / ' + rows.length + ' төхөөрөмж';
                }

                function choose(row) {
                    selected = row;
                    input.value = row ? row.id : '';
                    label.textContent = row ? row.code + ' · ' + row.name + (row.kit > 1 ? ' — иж бүрдэл: ' + row.kit + ' төхөөрөмж' : '') : 'Төхөөрөмж сонгох…';
                    label.classList.toggle('text-slate-400', !row);
                    label.classList.toggle('text-slate-900', !!row);
                    update();
                }

                function destination() {
                    const checked = document.querySelector('input[name="destination"]:checked');
                    return checked ? checked.value : 'user';
                }
                function update() {
                    const dest = destination();
                    const holder = selected && selected.holder;
                    const branch = selected && selected.branch;

                    panels.forEach(function (panel) {
                        const active = panel.dataset.dest === dest;
                        panel.classList.toggle('hidden', !active);
                        panel.querySelectorAll('select, input').forEach(function (el) { el.disabled = !active; el.required = active; });
                    });
                    document.querySelectorAll('select.transfer-user').forEach(function (select) {
                        Array.from(select.options).forEach(function (o) {
                            if (!o.value) return;
                            o.hidden = o.disabled = (!!holder && o.value === holder) || (!!branch && o.dataset.branch !== branch);
                        });
                        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
                    });

                    from.classList.toggle('hidden', !selected);
                    if (selected) from.textContent = 'Одоо: ' + selected.place + '.' + (selected.items ? ' Иж бүрдлээр нь шилжинэ: ' + selected.items + '.' : '');
                }

                pick.addEventListener('click', function () {
                    render();
                    dialog.showModal();
                    document.getElementById('dev-q').focus();
                });
                filters.forEach(function (el) { el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', render); });
                tbody.addEventListener('click', function (e) {
                    const tr = e.target.closest('tr[data-id]');
                    if (!tr) return;
                    choose(rows.find(function (r) { return String(r.id) === tr.dataset.id; }));
                    dialog.close();
                });
                dialog.querySelector('[data-close]').addEventListener('click', function () { dialog.close(); });
                dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
                // Төхөөрөмж сонгоогүй бол илгээхгүй, цонхыг нээнэ
                input.form.addEventListener('submit', function (e) {
                    if (!input.value) { e.preventDefault(); pick.click(); }
                });
                radios.forEach(function (r) { r.addEventListener('change', update); });

                // Өмнө сонгосон (алдаатай буцсан эсвэл ?computer=) төхөөрөмж: иж бүрдлийн аль ч гишүүнээр
                const preset = @json($selectedId);
                choose(preset ? rows.find(function (r) { return r.ids.indexOf(preset) !== -1; }) || null : null);
            })();
            </script>
        @endif
    </form>

    <h2 class="!mb-0 pt-2 text-base font-bold">Шилжүүлгийн түүх</h2>

    <form method="GET" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, төхөөрөмж, дугаар, ажилтан..."
               class="min-w-60 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
        <button class="rounded-lg !border-slate-800 !bg-slate-800 px-4 py-2 text-sm !text-white">Хайх</button>
        @if (filled(request('q')))<a href="{{ route('transfers.index') }}" class="text-sm !text-slate-500 hover:underline">Цэвэрлэх</a>@endif
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="w-12 px-4 py-3">№</th>
                    <th class="px-4 py-3">Огноо</th>
                    <th class="px-4">Төхөөрөмж</th>
                    <th class="px-4">Хэнээс</th>
                    <th class="px-4">Хэнд</th>
                    <th class="px-4">Төлөв</th>
                    <th class="px-4 text-right">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($transfers as $t)
                <tr class="align-top">
                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-500">#{{ $transfers->firstItem() + $loop->index }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500">
                        {{ $t->created_at->format('Y-m-d H:i') }}
                        @if ($t->received_at)<div class="text-[11px]">IT: {{ $t->received_at->format('m-d H:i') }}{{ $t->receiver ? ' · '.$t->receiver->name : '' }}</div>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($t->computer && ! $t->computer->trashed())
                            <a href="{{ route('computers.show', $t->computer) }}" class="font-medium hover:underline">{{ $t->computer->name }}</a>
                        @else
                            <span class="font-medium">{{ $t->computer->name ?? '#'.$t->computer_id }}</span>
                        @endif
                        <div class="font-mono text-xs text-slate-400">{{ $t->computer?->inventory_number }}</div>
                    </td>
                    <td class="px-4 py-3">
                        {{ $t->fromText() }}
                        @if ($t->reason)<div class="text-xs text-slate-500">«{{ $t->reason }}»</div>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($t->destinationText())
                            <b>{{ $t->destinationText() }}</b>
                        @elseif (in_array($t->status, ['completed', 'transferred'], true))
                            <b>{{ $t->issuedTo->name ?? '—' }}</b>
                            @if ($t->toUser && $t->to_user_id !== $t->issued_to_user_id)<div class="text-xs text-slate-400">санал: {{ $t->toUser->name }}</div>@endif
                        @else
                            {{ $t->toUser->name ?? '—' }}
                        @endif
                    </td>
                    <td class="px-4 py-3"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs {{ $t->statusClass() }}">{{ $t->statusLabel() }}</span></td>
                    <td class="px-4 py-3 text-right">
                        @if ($t->status === 'pending')
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('transfers.receive', $t) }}"
                                      onsubmit="return confirm('Төхөөрөмжийг {{ addslashes($t->fromUser->name ?? '') }}-аас хүлээн авч, агуулахад оруулах уу?')">
                                    @csrf
                                    <button class="!border-sky-600 !bg-sky-600 !px-3 !py-1.5 !text-xs !text-white">IT-д хүлээн авах</button>
                                </form>
                                <form method="POST" action="{{ route('transfers.reject', $t) }}">
                                    @csrf
                                    <button class="btn-danger !border-rose-200 !bg-white !px-3 !py-1.5 !text-xs !text-rose-600">Татгалзах</button>
                                </form>
                            </div>
                        @elseif ($t->status === 'received')
                            <form method="POST" action="{{ route('transfers.issue', $t) }}" class="flex flex-wrap justify-end gap-2">
                                @csrf
                                <select name="user_id" required class="{{ $inp }}" aria-label="Олгох ажилтан">
                                    <option value="">Олгох ажилтан</option>
                                    @foreach ($employees as $e)
                                        @continue($e->id === $t->from_user_id)
                                        <option value="{{ $e->id }}" @selected($e->id === $t->to_user_id)>{{ $e->name }}{{ $e->id === $t->to_user_id ? ' (санал)' : '' }}</option>
                                    @endforeach
                                </select>
                                <button class="!border-emerald-600 !bg-emerald-600 !px-3 !py-1.5 !text-xs !text-white">Олгох</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Шилжүүлэг байхгүй.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $transfers->links() }}
</div>
@endsection
