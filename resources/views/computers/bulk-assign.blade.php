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
    $field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <div>
        <div class="text-xs text-slate-500">Удирдлага</div>
        <h1 class="!mb-0 text-xl font-bold">Төхөөрөмж олгох</h1>
        <p class="mt-1 text-sm text-slate-500">
            Ажилтнаа сонгоод процессор, дэлгэц, mouse, keyboard, printer зэрэг чөлөөтэй төхөөрөмжүүдээс олныг нэг дор олгоно.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- 1. Ажилтан сонгох --}}
    <form method="GET" action="{{ route('computers.bulk-assign') }}"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label for="user_id" class="mb-2 block text-sm font-semibold text-slate-700">1. Ажилтан сонгох</label>
        <div class="flex flex-wrap gap-2">
            <select name="user_id" id="user_id" onchange="this.form.submit()" class="{{ $field }} min-w-0 flex-1">
                <option value="">— Ажилтан сонгох —</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected($selected?->id === $employee->id)>
                        {{ $employee->name }} ({{ $employee->username ?: $employee->email }}) · {{ $employee->roleLabel() }}
                    </option>
                @endforeach
            </select>
            <noscript><button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm">Сонгох</button></noscript>
        </div>
    </form>

    @if ($selected)
        {{-- Ажилтны одоогийн төхөөрөмжүүд --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">
                {{ $selected->name }}-ийн одоогийн төхөөрөмжүүд
                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $current->count() }}</span>
            </h2>

            @forelse ($current as $item)
                @php $key = $item->category?->value ?? 'none'; @endphp
                <div class="flex items-center gap-3 border-t border-slate-100 py-2 text-sm first:border-t-0">
                    <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                    <span class="font-medium text-slate-800">{{ $item->name }}</span>
                    <span class="text-slate-500">{{ $labelOf($key) }}@if ($item->inventory_number) · {{ $item->inventory_number }}@endif</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Одоогоор төхөөрөмж оноогдоогүй байна.</p>
            @endforelse
        </section>

        {{-- 2. Олгох төхөөрөмж сонгох --}}
        <form method="POST" action="{{ route('computers.bulk-assign.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="user_id" value="{{ $selected->id }}">

            <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">
                    2. Олгох төхөөрөмж сонгох
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">чөлөөтэй {{ $totalAvailable }}</span>
                </h2>

                @if ($totalAvailable === 0)
                    <p class="text-sm text-slate-500">
                        Чөлөөтэй төхөөрөмж байхгүй байна. Эхлээд
                        <a href="{{ route('computers.bulk-create', ['user_id' => $selected->id]) }}" class="font-medium text-blue-600 hover:underline">багцаар бүртгэнэ</a>
                        (процессор, дэлгэц, mouse ... нэг дор).
                    </p>
                @else
                    @foreach ($order as $key)
                        @continue(! $available->has($key))

                        <div data-group>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                                {{ $labelOf($key) }}
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $available[$key]->count() }}</span>
                                <button type="button" data-select-all class="ml-auto !border-0 !bg-transparent !p-0 !text-xs font-medium text-blue-600 hover:underline">Бүгдийг сонгох</button>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($available[$key] as $computer)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                        <input type="checkbox" name="computer_ids[]" value="{{ $computer->id }}"
                                               class="mt-1 h-4 w-4 shrink-0" @checked(in_array($computer->id, $picked, true))>
                                        <span class="min-w-0 text-sm">
                                            <span class="block font-medium text-slate-800">{{ $computer->name }}</span>
                                            <span class="block text-xs text-slate-500">
                                                {{ $computer->inventory_number }}
                                                @if (trim($computer->brand.' '.$computer->model) !== '') · {{ trim($computer->brand.' '.$computer->model) }}@endif
                                                @if ($computer->serial_number) · S/N {{ $computer->serial_number }}@endif
                                                @if ($computer->status) · {{ $computer->status->label() }}@endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <label for="note" class="mb-1 block text-sm font-medium text-slate-700">Тайлбар (заавал биш)</label>
                        <input type="text" name="note" id="note" maxlength="1000" value="{{ old('note') }}"
                               placeholder="Жишээ: Шинэ ажилтны багц" class="{{ $field }}">
                    </div>
                @endif
            </section>

            @if ($totalAvailable > 0)
                <div class="sticky bottom-0 -mx-4 flex items-center justify-between gap-3 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
                    <span class="text-sm text-slate-500">Сонгосон: <b id="picked-count" class="text-slate-800">0</b></span>
                    <button type="submit" id="assign-btn"
                            class="rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $selected->name }}-д олгох
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>

<script>
(function () {
    const boxes = document.querySelectorAll('input[name="computer_ids[]"]');
    const counter = document.getElementById('picked-count');
    const btn = document.getElementById('assign-btn');
    if (!counter || !btn) return;

    function update() {
        const n = Array.from(boxes).filter(function (b) { return b.checked; }).length;
        counter.textContent = n;
        btn.disabled = n === 0;
    }

    boxes.forEach(function (b) { b.addEventListener('change', update); });

    // Ангилал бүрийн "Бүгдийг сонгох": бүгд сонгогдсон бол сонголтыг арилгана
    document.querySelectorAll('[data-select-all]').forEach(function (link) {
        link.addEventListener('click', function () {
            const group = link.closest('[data-group]').querySelectorAll('input[name="computer_ids[]"]');
            const allChecked = Array.from(group).every(function (b) { return b.checked; });
            group.forEach(function (b) { b.checked = !allChecked; });
            update();
        });
    });

    update();
})();
</script>
@endsection