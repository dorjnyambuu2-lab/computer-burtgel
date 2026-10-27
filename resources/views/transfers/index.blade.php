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
                <label class="block text-sm font-medium text-slate-700">Төхөөрөмж <span class="text-rose-500">*</span>
                    <select name="computer_id" id="transfer-device" required class="{{ $field }}">
                        <option value="">— Сонгох —</option>
                        @foreach ($sets->groupBy(fn ($g) => $g->first()->place.($showBranch ? ' · '.($g->first()->branch?->name ?? 'Салбаргүй') : '')) as $place => $list)
                            <optgroup label="{{ $place }}">
                                @foreach ($list as $set)
                                    @php $d = $set->first(); @endphp
                                    <option value="{{ $d->id }}" data-holder="{{ $d->assigned_user_id }}" data-branch="{{ $d->branch_id }}" data-place="{{ $d->place }}"
                                            data-items="{{ $set->count() > 1 ? $set->map(fn ($i) => $i->inventory_number.' '.$i->name)->implode(', ') : '' }}"
                                            @selected($selectedId !== '' && $set->contains(fn ($i) => (string) $i->id === $selectedId))>
                                        {{ $d->inventory_number }} · {{ $d->name }}{{ $d->category ? ' ('.$d->category->label().')' : '' }}{{ $set->count() > 1 ? ' — иж бүрдэл: '.$set->count().' төхөөрөмж' : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>

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
            <script>
            // Сонгосон чиглэлийн талбарыг л харуулж (бусдыг идэвхгүй болгож илгээхгүй), төхөөрөмжийн одоогийн байдал,
            // иж бүрдлийн гишүүдийг харуулна. Хэрэглэгчийн жагсаалтаас одоогийн эзэмшигч, өөр салбарынхныг хасна.
            (function () {
                const device = document.getElementById('transfer-device');
                const from = document.getElementById('transfer-from');
                const radios = document.querySelectorAll('input[name="destination"]');
                const panels = document.querySelectorAll('[data-dest]');
                function destination() {
                    const checked = document.querySelector('input[name="destination"]:checked');
                    return checked ? checked.value : 'user';
                }
                function update() {
                    const dest = destination();
                    const opt = device.selectedOptions[0];
                    const holder = opt && opt.dataset.holder;
                    const branch = opt && opt.dataset.branch;

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

                    from.classList.toggle('hidden', !opt || !opt.value);
                    if (opt && opt.value) from.textContent = 'Одоо: ' + opt.dataset.place + '.'
                        + (opt.dataset.items ? ' Иж бүрдлээр нь шилжинэ: ' + opt.dataset.items + '.' : '');
                }
                device.addEventListener('change', update);
                radios.forEach(function (r) { r.addEventListener('change', update); });
                update();
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
