@extends('layouts.app')
@section('title', 'Алба хэлтэс')
@section('content')
@php
    $in = 'h-10 w-full !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
    $sm = 'h-9 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 focus:!border-blue-400';
    $isSuper = auth()->user()->isSuperadmin();
    // «Алба», «Нэгж» таб: ижил хуудас, өөр жагсаалт (DepartmentController, UnitController)
    $isUnit = $kind === 'unit';
    $route = $isUnit ? 'units' : 'departments';
    $word = $isUnit ? 'нэгж' : 'алба';
    $Word = $isUnit ? 'Нэгж' : 'Алба';
    $wordAcc = $isUnit ? 'нэгжийг' : 'албыг';
    $filtered = filled(request('q')) || filled(request('branch')) || filled(request('unit'));
    // Салбар бүрээр бүлэглэнэ (Админд ганц салбар)
    $groups = $departments->groupBy(fn ($d) => $d->branch?->name ?? 'Салбаргүй');
    // Мөрийн баганууд: нэр | төхөөрөмж | (нэгжид: алба) | ажилтан | засах
    $grid = $isUnit ? 'sm:grid-cols-[1fr_6.5rem_5rem_5.5rem_5.5rem]' : 'sm:grid-cols-[1fr_6.5rem_5.5rem_5.5rem]';
    $addOpen = old('name') !== null || $errors->hasAny(['name', 'branch_id', 'unit_id']);
@endphp

<div class="mx-auto max-w-5xl space-y-5">
    {{-- Гарчиг + нэмэх товч --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Алба хэлтэс</h1>
        <button type="button" data-add-toggle
                class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-4 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">+ {{ $Word }} нэмэх</button>
    </div>

    {{-- Табууд + шүүлтүүр нэг мөрөнд --}}
    <div class="flex flex-wrap items-end justify-between gap-x-3 border-b border-slate-200">
        <nav class="flex gap-1" aria-label="Жагсаалт">
            @foreach (['department' => ['Алба', 'departments.index'], 'unit' => ['Нэгж', 'units.index']] as $tab => [$label, $tabRoute])
                <a href="{{ route($tabRoute, array_filter(['branch' => request('branch')])) }}" @if ($tab === $kind) aria-current="page" @endif
                   class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold {{ $tab === $kind ? 'border-blue-600 !text-blue-700' : 'border-transparent !text-slate-500 hover:!text-slate-800' }}">
                    {{ $label }}
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tab === $kind ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$tab] }}</span>
                </a>
            @endforeach
        </nav>
        {{-- Шүүлтүүр: нэр, салбар (Супер админ), нэгж (Алба табд) — сонгоход шууд шүүнэ --}}
        <form method="GET" action="{{ route($route.'.index') }}" class="mb-2 flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $isUnit ? 'Нэгжийн' : 'Албаны' }} нэрээр хайх" class="{{ $sm }} w-48">
            @if ($isSuper)
                <select name="branch" onchange="this.form.submit()" class="{{ $sm }} w-40" aria-label="Салбараар шүүх">
                    <option value="">Бүх салбар</option>
                    @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) request('branch') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
            @endif
            @unless ($isUnit)
                <select name="unit" onchange="this.form.submit()" class="{{ $sm }} w-44" aria-label="Нэгжээр шүүх">
                    <option value="">Бүх нэгж</option>
                    <option value="none" @selected(request('unit') === 'none')>Нэгжгүй</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) request('unit') === (string) $u->id)>{{ $u->name }}@if ($isSuper && ! request('branch')) ({{ $branches[$u->branch_id] ?? '—' }})@endif</option>@endforeach
                </select>
            @endunless
            @if ($filtered)
                <a href="{{ route($route.'.index') }}" class="px-1 text-sm !text-slate-500 hover:!text-slate-800 hover:underline">Цэвэрлэх</a>
            @endif
        </form>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Шинэ алба / нэгж: «+ ... нэмэх» товчоор нээгдэнэ (алдаа гарсан бол нээлттэй) --}}
    @php
        $cols = match (true) {
            $isSuper && ! $isUnit => 'sm:grid-cols-[2fr_1fr_1fr_auto]',
            $isSuper || ! $isUnit => 'sm:grid-cols-[2fr_1fr_auto]',
            default => 'sm:grid-cols-[1fr_auto]',
        };
    @endphp
    <form method="POST" action="{{ route($route.'.store') }}" data-dept-add @unless ($addOpen) hidden @endunless
          class="grid gap-2 rounded-2xl border border-blue-100 bg-blue-50/40 p-4 {{ $cols }}">
        @csrf
        <h2 class="!mb-0 text-sm font-semibold text-slate-700 sm:col-span-full">Шинэ {{ $word }}</h2>
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
                {{-- Супер админд салбараар бүлэглэнэ: нэгж сонгоход салбар нь автоматаар сонгогдоно --}}
                @foreach ($units->groupBy('branch_id') as $branchId => $branchUnits)
                    @if ($isSuper)<optgroup label="{{ $branches[$branchId] ?? 'Салбаргүй' }}" data-branch="{{ $branchId }}">@endif
                    @foreach ($branchUnits as $u)<option value="{{ $u->id }}" data-branch="{{ $u->branch_id }}" @selected((string) old('unit_id') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                    @if ($isSuper)</optgroup>@endif
                @endforeach
            </select>
        @endunless
        <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Хадгалах</button>
    </form>

    @forelse ($groups as $branchName => $list)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {{-- Салбар + баганын гарчиг --}}
            <div class="grid grid-cols-1 items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-2.5 {{ $grid }}">
                <h2 class="!mb-0 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ $isSuper ? $branchName : $Word }} <span class="ml-1 font-normal normal-case tracking-normal text-slate-400">· {{ $list->count() }}</span>
                </h2>
                <span class="hidden text-right text-[11px] font-semibold uppercase tracking-wide text-slate-400 sm:block">Төхөөрөмж</span>
                @if ($isUnit)<span class="hidden text-right text-[11px] font-semibold uppercase tracking-wide text-slate-400 sm:block">Алба</span>@endif
                <span class="hidden text-right text-[11px] font-semibold uppercase tracking-wide text-slate-400 sm:block">Ажилтан</span>
            </div>
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
                        <li class="flex items-center gap-2 px-5 pb-1 pt-3 text-xs font-semibold text-slate-500">
                            <span class="h-1.5 w-1.5 rounded-full {{ $d->unit ? 'bg-blue-500' : 'bg-slate-300' }}"></span>
                            {{ $d->unit ? $d->unit->name : 'Нэгжгүй' }}
                        </li>
                    @endif
                    <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-slate-50/60 {{ $grid }} {{ $isUnit ? '' : 'sm:pl-9' }}">
                        <span class="min-w-0 truncate font-medium text-slate-900">{{ $d->name }}</span>
                        {{-- Тоо: утсанд нэрийн доор, том дэлгэцэд баганаар --}}
                        <a href="{{ route('computers.index', array_filter([$isUnit ? 'q' : 'department' => $d->name, 'branch' => $isSuper ? $d->branch_id : null])) }}"
                           class="order-3 col-span-2 text-xs !text-slate-500 hover:!text-blue-700 sm:order-none sm:col-span-1 sm:text-right sm:text-sm {{ $d->computers_count ? 'sm:!text-slate-800' : 'sm:!text-slate-300' }}">
                            <span class="font-semibold">{{ $d->computers_count }}</span><span class="sm:hidden"> төхөөрөмж · {{ $isUnit ? $d->departments_count.' алба · ' : '' }}{{ $d->users_count }} ажилтан</span>
                        </a>
                        @if ($isUnit)
                            <span class="hidden text-right text-sm sm:block {{ $d->departments_count ? 'font-semibold text-slate-800' : 'text-slate-300' }}">{{ $d->departments_count }}</span>
                        @endif
                        <span class="hidden text-right text-sm sm:block {{ $d->users_count ? 'font-semibold text-slate-800' : 'text-slate-300' }}">{{ $d->users_count }}</span>
                        <details class="group relative justify-self-end" data-popover>
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
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-slate-400">{{ $filtered ? 'Шүүлтэд тохирох '.$word.' алга.' : $Word.' бүртгэгдээгүй байна.' }}</p>
    @endforelse
</div>

<script>
// «+ ... нэмэх»: нэмэх хайрцгийг нээж/хааж, нэрийн талбарт курсор
(function () {
    const form = document.querySelector('[data-dept-add]');
    document.querySelector('[data-add-toggle]').addEventListener('click', function () {
        form.hidden = !form.hidden;
        if (!form.hidden) form.querySelector('[name="name"]').focus();
    });
})();
</script>

@if ($isSuper && ! $isUnit)
<script>
// Шинэ алба: салбар сонгосон бол тэр салбарын нэгжүүд л; сонгоогүй бол бүх салбарынх (бүлгээр).
// Нэгж сонгоход салбар нь автоматаар сонгогдоно
(function () {
    const form = document.querySelector('[data-dept-add]');
    const branch = form.querySelector('[name="branch_id"]');
    const unit = form.querySelector('[name="unit_id"]');
    function filter() {
        unit.querySelectorAll('optgroup').forEach(function (g) {
            const off = !!branch.value && g.dataset.branch !== branch.value;
            g.hidden = g.disabled = off;
        });
        if (unit.selectedOptions[0] && unit.selectedOptions[0].parentElement.disabled) unit.value = '';
    }
    branch.addEventListener('change', filter);
    unit.addEventListener('change', function () {
        const o = unit.selectedOptions[0];
        if (o && o.dataset.branch && branch.value !== o.dataset.branch) { branch.value = o.dataset.branch; filter(); }
    });
    filter();
})();
</script>
@endif
@endsection
