@extends('layouts.app')

@section('title', 'Багцаар бүртгэх')

@section('content')
@php
    // Урьдчилан бөглөсөн ангилалтай мөрүүд: алдаа гарвал хэрэглэгчийн оруулсныг, скриптээр уншсан бол түүнийг,
    // үгүй бол үндсэн 5 төхөөрөмжийг
    $defaults = ['processor', 'monitor', 'mouse', 'keyboard', 'printer'];
    $rows = array_values(old('rows') ?: ($detectedRows ?: array_map(fn ($c) => ['category' => $c], $defaults)));
    $field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $chosenUser = old('user_id', $selectedUserId);
@endphp

<div class="mx-auto max-w-6xl space-y-4">
    <h1 class="!mb-0 text-xl font-bold">Багцаар бүртгэх</h1>

    @if ($errors->any())
        <div class="space-y-1 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('computers.bulk-create.store') }}" class="space-y-4">
        @csrf

        {{-- Бүх төхөөрөмжид нийтлэг мэдээлэл --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
                <span class="grid h-6 w-6 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                Нийтлэг мэдээлэл <span class="font-normal text-slate-400">· бүх төхөөрөмжид</span>
            </h2>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label for="user_id" class="mb-1 block text-sm font-medium text-slate-700">Олгох ажилтан (заавал биш)</label>
                    <select name="user_id" id="user_id" class="{{ $field }}">
                        <option value="">— Олгохгүй, агуулахад —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) $chosenUser === (string) $employee->id)>
                                {{ $employee->name }} ({{ $employee->username ?: $employee->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="mb-1 block text-sm font-medium text-slate-700">Салбар <span class="text-rose-500">*</span></label>
                    @if (auth()->user()->isBranchScoped())
                        <div class="{{ $field }} bg-slate-50 text-slate-700">{{ auth()->user()->branch?->name ?? 'Салбар оноогоогүй' }}</div>
                    @else
                        @php
                            $branchList = \App\Models\Branch::query()->orderBy('name')->get(['id', 'name', 'code']);
                            $branchValue = (string) old('branch_id', $branchList->count() === 1 ? $branchList->first()->id : '');
                        @endphp
                        <select name="branch_id" id="branch_id" required class="{{ $field }}">
                            <option value="">— Салбар сонгох —</option>
                            @foreach ($branchList as $b)
                                <option value="{{ $b->id }}" @selected($branchValue === (string) $b->id)>{{ $b->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label for="department" class="mb-1 block text-sm font-medium text-slate-700">Алба хэлтэс</label>
                    <input type="text" name="department" id="department" list="department-list" value="{{ old('department') }}" class="{{ $field }}">
                    <datalist id="department-list">
                        @foreach ($departments as $department)
                            <option value="{{ $department }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label for="location" class="mb-1 block text-sm font-medium text-slate-700">Байршил</label>
                    <input type="text" name="location" id="location" list="sg-location" autocomplete="off" value="{{ old('location') }}" placeholder="Байр, давхар, өрөө" class="{{ $field }}">
                </div>
                <div>
                    <label for="purchase_date" class="mb-1 block text-sm font-medium text-slate-700">Худалдан авсан огноо</label>
                    <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date', today()->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="warranty_months" class="mb-1 block text-sm font-medium text-slate-700">Баталгааны хугацаа</label>
                    @php $wm = (string) old('warranty_months', '12'); @endphp
                    <select name="warranty_months" id="warranty_months" class="{{ $field }}">
                        <option value="" @selected($wm === '')>Баталгаагүй</option>
                        @foreach (\App\Support\Warranty::OPTIONS as $months => $text)
                            <option value="{{ $months }}" @selected($wm === (string) $months)>{{ $text }}</option>
                        @endforeach
                        <option value="manual" @selected($wm === 'manual')>Өөр (огноог гараар оруулна)</option>
                    </select>
                </div>
                <div>
                    <label for="warranty_until" class="mb-1 block text-sm font-medium text-slate-700">Баталгаа дуусах огноо</label>
                    <input type="date" name="warranty_until" id="warranty_until" value="{{ old('warranty_until') }}" class="{{ $field }}">
                </div>
            </div>
        </section>

        {{-- Төхөөрөмжүүд --}}
        <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <span class="grid h-6 w-6 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                Төхөөрөмжүүд <span id="row-count" class="font-normal text-slate-400"></span>
            </h2>

            <div id="rows" class="space-y-3">
                @foreach ($rows as $i => $row)
                    @include('computers._bulk_row', ['i' => $i, 'row' => $row])
                @endforeach
            </div>

            {{-- Ангиллаар нь шууд мөр нэмэх --}}
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs font-medium text-slate-500">Нэмэх:</span>
                @foreach (\App\Enums\EquipmentCategory::cases() as $category)
                    <button type="button" data-add="{{ $category->value }}"
                            class="!rounded-full !border-slate-300 !bg-white !px-3 !py-1 !text-xs !font-medium !text-slate-700 hover:!bg-slate-100">
                        + {{ $category->label() }}
                    </button>
                @endforeach
            </div>
        </section>

        <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
            <span id="summary" class="mr-auto text-sm text-slate-500"></span>
            <a href="{{ route('computers.index') }}"
               class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold !text-slate-700 hover:bg-slate-100">Цуцлах</a>
            <button type="submit" id="submit"
                    class="rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                Бүртгэх
            </button>
        </div>
    </form>
</div>

@include('computers._spec_datalists')
@include('computers._warranty_script', ['purchaseId' => 'purchase_date', 'monthsId' => 'warranty_months', 'untilId' => 'warranty_until'])

{{-- Шинэ мөрийн загвар --}}
<template id="row-template">
    @include('computers._bulk_row', ['i' => '__INDEX__', 'row' => []])
</template>

<script>
(function () {
    const container = document.getElementById('rows');
    const template = document.getElementById('row-template');
    const cfg = @json(\App\Support\SpecSuggestions::itemConfig());
    let next = @json(count($rows));

    // Ангиллаас хамааран талбаруудыг харуулах/нуух, сонголтын жагсаалтыг солих.
    // Нуусан талбар disabled болж илгээгдэхгүй (утга нь хадгалагдана, ангиллаа буцаавал гарч ирнэ)
    function applyRow(row) {
        const cat = row.querySelector('[data-category]').value;
        const computer = cfg.groupOf[cat] === 'computer';
        const allowed = cfg.groups[cfg.groupOf[cat] || 'basic'];

        row.querySelectorAll('[data-field]').forEach(function (wrap) {
            const f = wrap.dataset.field;
            const input = wrap.querySelector('input');
            const show = allowed.indexOf(f) !== -1;
            wrap.classList.toggle('hidden', !show);
            input.disabled = !show;

            let list = '';
            if (f === 'brand') list = computer ? 'sg-brand-computer' : 'sg-brand-' + cat;
            if (f === 'model') list = computer ? 'sg-model-computer' : 'sg-model-' + cat;
            if (['cpu', 'ram_gb', 'storage', 'os'].indexOf(f) !== -1) list = 'sg-' + f;
            if (f === 'specs') { list = 'sg-specs-' + cat; input.placeholder = cfg.specsPlaceholder[cat] || ''; }
            list ? input.setAttribute('list', list) : input.removeAttribute('list');
        });
    }

    // Мөр бүрийг дугаарлаж, хоосон (юу ч бөглөөгүй) мөрийг бүдгэрүүлнэ: тэд бүртгэгдэхгүй
    function refresh() {
        const rows = Array.from(container.querySelectorAll('[data-row]'));
        let filled = 0;
        rows.forEach(function (row, i) {
            row.querySelector('[data-number]').textContent = i + 1;
            const has = Array.from(row.querySelectorAll('input')).some(function (el) { return !el.disabled && el.value.trim() !== ''; });
            row.classList.toggle('opacity-60', !has);
            row.querySelector('[data-empty]').classList.toggle('hidden', has);
            if (has) filled++;
        });

        document.getElementById('row-count').textContent = '(' + rows.length + ')';
        document.getElementById('summary').textContent = filled === rows.length
            ? ''
            : (rows.length - filled) + ' хоосон мөр бүртгэгдэхгүй';
        const submit = document.getElementById('submit');
        submit.textContent = filled ? filled + ' төхөөрөмж бүртгэх' : 'Бүртгэх';
        submit.disabled = filled === 0;
        submit.classList.toggle('opacity-50', filled === 0);
    }

    function addRow(cat, values) {
        container.insertAdjacentHTML('beforeend', template.innerHTML.split('__INDEX__').join(next++));
        const row = container.lastElementChild;
        row.querySelector('[data-category]').value = cat;
        Object.keys(values || {}).forEach(function (f) {
            const input = row.querySelector('[data-field="' + f + '"] input');
            if (input) input.value = values[f];
        });
        applyRow(row);
        refresh();
        return row;
    }

    container.querySelectorAll('[data-row]').forEach(applyRow);
    refresh();

    container.addEventListener('change', function (e) {
        if (e.target.matches('[data-category]')) {
            applyRow(e.target.closest('[data-row]'));
            refresh();
        }
    });
    container.addEventListener('input', refresh);

    container.addEventListener('click', function (e) {
        const row = e.target.closest('[data-row]');
        if (!row) return;
        if (e.target.closest('[data-remove]')) {
            row.remove();
            refresh();
        } else if (e.target.closest('[data-dup]')) {
            // Ижил загварын төхөөрөмж (жишээ нь 2 дэлгэц): үйлдвэрлэгч, загвар, үзүүлэлтийг хуулна, серийн дугаарыг биш
            const copy = {};
            ['brand', 'model', 'specs'].forEach(function (f) {
                const input = row.querySelector('[data-field="' + f + '"] input');
                if (input && !input.disabled) copy[f] = input.value;
            });
            const added = addRow(row.querySelector('[data-category]').value, copy);
            row.after(added);
            refresh();
            added.querySelector('[data-field="serial_number"] input').focus();
        }
    });

    document.querySelectorAll('[data-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            addRow(button.dataset.add).querySelector('[data-field="brand"] input').focus();
        });
    });
})();

</script>
@endsection
