@extends('layouts.app')
@section('title', 'Алба хэлтэс')
@section('content')
@php
    $in = 'h-10 w-full !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $isSuper = auth()->user()->isSuperadmin();
    // «Алба», «Нэгж» таб: ижил хуудас, өөр жагсаалт (DepartmentController, UnitController)
    $isUnit = $kind === 'unit';
    $route = $isUnit ? 'units' : 'departments';
    $word = $isUnit ? 'нэгж' : 'алба';
    $Word = $isUnit ? 'Нэгж' : 'Алба';
    $wordAcc = $isUnit ? 'нэгжийг' : 'албыг';
    // Салбар бүрээр бүлэглэнэ (Админд ганц салбар)
    $groups = $departments->groupBy(fn ($d) => $d->branch?->name ?? 'Салбаргүй');
@endphp

<div class="mx-auto max-w-5xl space-y-4">
    <div>
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Алба хэлтэс</h1>
    </div>

    <nav class="flex gap-1 border-b border-slate-200" aria-label="Жагсаалт">
        @foreach (['department' => ['Алба', 'departments.index'], 'unit' => ['Нэгж', 'units.index']] as $tab => [$label, $tabRoute])
            <a href="{{ route($tabRoute, array_filter(['branch' => request('branch')])) }}" @if ($tab === $kind) aria-current="page" @endif
               class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-semibold {{ $tab === $kind ? 'border-blue-600 !text-blue-700' : 'border-transparent !text-slate-500 hover:!text-slate-800' }}">
                {{ $label }}
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tab === $kind ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$tab] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Шинэ алба / нэгж --}}
    @php
        $cols = match (true) {
            $isSuper && ! $isUnit => 'sm:grid-cols-[2fr_1fr_1fr_auto]',
            $isSuper || ! $isUnit => 'sm:grid-cols-[2fr_1fr_auto]',
            default => 'sm:grid-cols-[1fr_auto]',
        };
    @endphp
    <form method="POST" action="{{ route($route.'.store') }}" data-dept-add
          class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm {{ $cols }}">
        @csrf
        <input name="name" value="{{ old('name') }}" required maxlength="255" class="{{ $in }}"
               placeholder="{{ $isUnit ? 'Нэгжийн нэр (жишээ: Захиргааны нэгж)' : 'Албаны нэр (жишээ: Мэдээллийн технологийн алба)' }}">
        @if ($isSuper)
            <select name="branch_id" required class="{{ $in }}">
                <option value="">— Салбар —</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) old('branch_id', request('branch')) === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        @endif
        @unless ($isUnit)
            {{-- Харьяалах нэгж (заавал биш): Салбар → Нэгж → Алба --}}
            <select name="unit_id" class="{{ $in }}" aria-label="Нэгж">
                <option value="">— Нэгжгүй —</option>
                @foreach ($units as $u)<option value="{{ $u->id }}" data-branch="{{ $u->branch_id }}" @selected((string) old('unit_id') === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        @endunless
        <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-4 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">+ {{ $Word }} нэмэх</button>
    </form>

    @forelse ($groups as $branchName => $list)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($isSuper)
                <h2 class="!mb-0 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $branchName }} <span class="ml-1 font-normal normal-case tracking-normal text-slate-400">· {{ $list->count() }} {{ $word }}</span></h2>
            @endif
            <ul class="divide-y divide-slate-100">
                {{-- Алба: нэгж бүрээр бүлэглэнэ, нэгжгүй нь сүүлд --}}
                @php
                    if (! $isUnit) {
                        $list = $list->sortBy(fn ($d) => [$d->unit ? 0 : 1, $d->unit?->name, $d->name])->values();
                    }
                    $prevUnit = false;
                @endphp
                @foreach ($list as $d)
                    @php $siblings = $list->where('id', '!=', $d->id); $used = $d->computers_count || $d->users_count; @endphp
                    @if (! $isUnit && $prevUnit !== ($d->unit?->name))
                        @php $prevUnit = $d->unit?->name; @endphp
                        <li class="bg-slate-50 px-5 py-1.5 text-xs font-semibold text-slate-500">{{ $d->unit ? 'Нэгж: '.$d->unit->name : 'Нэгжгүй' }}</li>
                    @endif
                    <li class="px-5 py-3 {{ $isUnit ? '' : 'sm:pl-8' }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="min-w-0 flex-1 font-semibold text-slate-900">{{ $d->name }}</span>
                            <a href="{{ route('computers.index', array_filter([$isUnit ? 'q' : 'department' => $d->name, 'branch' => $isSuper ? $d->branch_id : null])) }}"
                               class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs !text-slate-600 hover:bg-slate-200">{{ $d->computers_count }} төхөөрөмж</a>
                            @if ($isUnit)
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs text-blue-700">{{ $d->departments_count }} алба</span>
                            @endif
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $d->users_count }} ажилтан</span>
                            <details class="group relative" data-popover>
                                <summary class="flex h-8 cursor-pointer list-none items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 [&::-webkit-details-marker]:hidden">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                                    Засах
                                </summary>
                                <div data-popover-panel class="absolute right-0 z-10 mt-1 w-80 space-y-3 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                                    {{-- Нэр солих: төхөөрөмж, ажилтан дээр шууд солигдоно --}}
                                    <form method="POST" action="{{ route($route.'.update', $d) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $d->name }}" required maxlength="255" class="{{ $in }}" aria-label="Шинэ нэр">
                                        <button class="h-10 shrink-0 !min-h-0 !rounded-lg !border-slate-800 !bg-slate-800 !px-3 !py-0 text-xs !text-white">Солих</button>
                                    </form>
                                    {{-- Алба: харьяалах нэгж (ижил салбарын) --}}
                                    @unless ($isUnit)
                                        <form method="POST" action="{{ route($route.'.update', $d) }}" class="flex gap-2">
                                            @csrf @method('PUT')
                                            <select name="unit_id" class="{{ $in }}" aria-label="Нэгж">
                                                <option value="">— Нэгжгүй —</option>
                                                @foreach ($units->where('branch_id', $d->branch_id) as $u)<option value="{{ $u->id }}" @selected((int) $d->unit_id === (int) $u->id)>{{ $u->name }}</option>@endforeach
                                            </select>
                                            <button class="h-10 shrink-0 !min-h-0 !rounded-lg !border-slate-800 !bg-slate-800 !px-3 !py-0 text-xs !text-white">Оноох</button>
                                        </form>
                                    @endunless
                                    {{-- Нэгтгэх: үсгийн алдаатай давхардлыг зөв рүү --}}
                                    @if ($siblings->isNotEmpty())
                                        <form method="POST" action="{{ route($route.'.merge', $d) }}" class="flex gap-2"
                                              onsubmit="return confirm(@js('«'.$d->name.'»-ын төхөөрөмж, ажилтныг сонгосон '.$word.' руу шилжүүлж, энэ '.$wordAcc.' устгах уу?'))">
                                            @csrf
                                            <select name="target_id" required class="{{ $in }}" aria-label="Нэгтгэх {{ $word }}">
                                                <option value="">Нэгтгэх {{ $word }}…</option>
                                                @foreach ($siblings as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                            </select>
                                            <button class="h-10 shrink-0 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-0 text-xs !text-white">Нэгтгэх</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route($route.'.destroy', $d) }}" onsubmit="return confirm(@js('«'.$d->name.'» '.$wordAcc.' устгах уу?'))">
                                        @csrf @method('DELETE')
                                        <button @disabled($used) title="{{ $used ? 'Төхөөрөмж, ажилтантай бол устгахгүй — нэгтгэнэ үү' : 'Устгах' }}"
                                                class="w-full !min-h-0 !rounded-lg !border !border-rose-200 !bg-white !px-3 !py-2 text-xs !text-rose-600 hover:!bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">Устгах</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-400">{{ $Word }} бүртгэгдээгүй байна.</p>
    @endforelse
</div>

@if ($isSuper && ! $isUnit)
<script>
// Шинэ алба: сонгосон салбарын нэгжүүд л
(function () {
    const form = document.querySelector('[data-dept-add]');
    const branch = form.querySelector('[name="branch_id"]');
    const unit = form.querySelector('[name="unit_id"]');
    function filter() {
        Array.from(unit.options).forEach(function (o) {
            if (!o.dataset.branch) return;
            o.hidden = o.disabled = o.dataset.branch !== branch.value;
        });
        if (unit.selectedOptions[0] && unit.selectedOptions[0].disabled) unit.value = '';
    }
    branch.addEventListener('change', filter);
    filter();
})();
</script>
@endif
@endsection
