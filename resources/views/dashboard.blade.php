@extends('layouts.app')
@section('title', 'Хянах самбар')

@php
    use App\Enums\ComputerStatus;
    use App\Models\Computer;

    // Төлөв бүрийн тоо (enum-ийн утгаар)
    $byStatus = Computer::query()->counted()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();
    $n = fn (ComputerStatus $s) => (int) ($byStatus[$s->value] ?? 0);
    $total = Computer::query()->counted()->count(); // mouse, keyboard тоолохгүй

    // Алба хэлтсээр: хоосон ба NULL нэг бүлэг («Хэлтэс оруулаагүй»), эхний 5-аас бусдыг «Бусад» болгож
    // нийлбэр нь нийт төхөөрөмжтэй үргэлж тэнцүү байна
    $deptRows = Computer::query()->counted()->selectRaw("NULLIF(TRIM(department), '') as k, count(*) as c")->groupBy('k')
        ->orderByDesc('c')->orderBy('k')->get();
    $byDept = $deptRows->take(5)->mapWithKeys(fn ($r) => [$r->k ?? 'Хэлтэс оруулаагүй' => (int) $r->c])->all();
    if ($deptRows->count() > 5) {
        $byDept['Бусад ('.($deptRows->count() - 5).' хэлтэс)'] = (int) $deptRows->slice(5)->sum('c');
    }
    $maxDept = max(1, ...array_values($byDept ?: [1]));
    $recent = Computer::latest()->limit(6)->get();
    try { $expiring = Computer::warrantyExpiringWithin(60)->limit(5)->get(); } catch (\Throwable $e) { $expiring = collect(); }

    $slices = [
        ['Шинэ', $n(ComputerStatus::New), '#6250d6', 'bg-violet-600'],
        ['Ашиглаж байгаа', $n(ComputerStatus::Active), '#2563eb', 'bg-blue-600'],
        ['Засварт', $n(ComputerStatus::InRepair), '#f59e0b', 'bg-amber-500'],
        ['Агуулахад', $n(ComputerStatus::InStorage), '#94a3b8', 'bg-slate-400'],
        ['Актласан', $n(ComputerStatus::Decommissioned), '#cbd5e1', 'bg-slate-300'],
    ];
    $active = $n(ComputerStatus::Active);
    $cards = [
        ['Нийт төхөөрөмж', $total, 'Бүртгэлтэй', 'text-blue-600', 'bg-blue-50', route('computers.index')],
        ['Ашиглаж байгаа', $active, $total ? round($active / $total * 100).'% ашиглалт' : '—', 'text-emerald-600', 'bg-emerald-50', route('computers.index', ['status' => ComputerStatus::Active->value])],
        ['Засварт байгаа', $n(ComputerStatus::InRepair), 'Засвар', 'text-amber-600', 'bg-amber-50', route('repairs.index')],
        ['Агуулахад байгаа', $n(ComputerStatus::InStorage), 'Хадгалагдаж буй', 'text-slate-600', 'bg-slate-100', route('computers.index', ['status' => ComputerStatus::InStorage->value])],
        ['Баталгаа дуусах', $expiring->count(), '60 хоногийн дотор', 'text-rose-600', 'bg-rose-50', route('computers.index', ['warranty' => 'expiring'])],
    ];
    $canAdd = auth()->user()?->canManageComputers();
    $offset = 0;
@endphp

@section('content')
@if (! auth()->user()->canManageComputers())
    @include('my.employee')
@else
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <div class="text-xs text-slate-500">Хянах самбар · Өнөөдөр шинэчлэгдсэн</div>
        <h1 class="text-xl font-bold">Компьютер бүртгэлийн систем</h1>
    </div>
    @if ($canAdd)
        <a href="{{ url('/computers/create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            <span class="text-lg leading-none">+</span> Компьютер бүртгэх
        </a>
    @endif
</div>

{{-- Stat cards --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ($cards as [$label, $val, $sub, $txt, $bg, $href])
        <a href="{{ $href }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-600">{{ $label }}</div>
                <div class="h-9 w-9 rounded-xl {{ $bg }}"></div>
            </div>
            <div class="mt-4 text-3xl font-bold">{{ $val }}</div>
            <div class="mt-2 text-xs {{ $txt }}">{{ $sub }}</div>
        </a>
    @endforeach
</div>

{{-- Charts --}}
<div class="mt-6 grid gap-4 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
            <div><h2 class="font-bold">Компьютерийн төлөв</h2><p class="text-xs text-slate-500">Одоогийн хөрөнгийн хуваарилалт</p></div>
            <a href="{{ url('/computers') }}" class="text-xs font-semibold text-blue-600">Бүгдийг харах</a>
        </div>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-8">
            <div class="relative h-44 w-44">
                <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="4"/>
                    @foreach ($slices as [$n, $v, $hex])
                        @if ($total && $v)
                            @php $len = $v / $total * 100; @endphp
                            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="{{ $hex }}" stroke-width="4"
                                    stroke-dasharray="{{ $len }} {{ 100 - $len }}" stroke-dashoffset="{{ -$offset }}"/>
                            @php $offset += $len; @endphp
                        @endif
                    @endforeach
                </svg>
                <div class="absolute inset-0 grid place-items-center text-center">
                    <div><div class="text-2xl font-bold">{{ $total }}</div><div class="text-xs text-slate-500">Төхөөрөмж</div></div>
                </div>
            </div>
            <ul class="space-y-2 text-sm">
                @foreach ($slices as [$n, $v, $hex, $bg])
                    <li class="flex items-center gap-3"><span class="h-2.5 w-2.5 rounded-full {{ $bg }}"></span><span class="w-32 text-slate-600">{{ $n }}</span><b>{{ $v }}</b></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
            <div><h2 class="font-bold">Алба хэлтсээр</h2><p class="text-xs text-slate-500">Төхөөрөмжийн тоо алба хэлтэс бүрээр (нийт {{ $total }})</p></div>
        </div>
        @if ($byDept)
            <div class="mt-6 flex h-44 items-end justify-around gap-3 border-b border-slate-200">
                @foreach ($byDept as $k => $c)
                    <div class="flex w-full max-w-16 flex-col items-center justify-end gap-1" style="height:100%">
                        <span class="text-xs font-semibold">{{ $c }}</span>
                        <div class="w-full rounded-t-lg bg-blue-500" style="height: {{ max(6, $c / $maxDept * 80) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-around gap-3 text-center text-[11px] text-slate-500">
                @foreach ($byDept as $k => $c)<span class="w-full max-w-20 truncate {{ $k === 'Хэлтэс оруулаагүй' ? 'italic text-slate-400' : '' }}" title="{{ $k }}">{{ $k }}</span>@endforeach
            </div>
        @else
            <p class="mt-8 text-sm text-slate-400">Мэдээлэл алга.</p>
        @endif
    </section>
</div>

{{-- Tables --}}
<div class="mt-6 grid gap-4 lg:grid-cols-3">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <div class="flex items-start justify-between p-6 pb-3">
            <div><h2 class="font-bold">Сүүлд бүртгэсэн компьютер</h2><p class="text-xs text-slate-500">Шинээр нэмэгдсэн төхөөрөмжүүд</p></div>
            <a href="{{ url('/computers') }}" class="text-xs font-semibold text-blue-600">Бүгдийг харах</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-6 py-3">Дугаар</th><th class="px-3 py-3">Нэр</th><th class="px-3 py-3">Хариуцагч</th><th class="px-3 py-3">Хэлтэс</th><th class="px-3 py-3">Төлөв</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recent as $c)
                        <tr class="cursor-pointer hover:bg-slate-50" onclick="location='{{ route('computers.show', $c) }}'">
                            <td class="px-6 py-3 font-mono text-xs"><span class="font-semibold text-slate-400">{{ $c->sequenceLabel() }}</span> {{ $c->inventory_number }}</td>
                            <td class="px-3 py-3 font-semibold">{{ $c->name ?: '—' }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $c->assigned_to ?: '—' }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $c->department ?: '—' }}</td>
                            <td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium {{ $c->status?->badgeClasses() }}">{{ $c->status?->label() ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Бүртгэл алга.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-bold">Баталгаа дуусах гэж буй</h2>
        <p class="text-xs text-slate-500">60 хоногийн дотор</p>
        <ul class="mt-4 divide-y divide-slate-100 text-sm">
            @forelse ($expiring as $c)
                <li class="py-3">
                    <a href="{{ route('computers.show', $c) }}" class="font-semibold hover:text-blue-600">{{ $c->name ?: $c->inventory_number }}</a>
                    <div class="text-xs text-rose-600">{{ $c->warranty_until?->format('Y-m-d') }}</div>
                </li>
            @empty
                <li class="py-6 text-slate-400">Байхгүй.</li>
            @endforelse
        </ul>
    </section>
</div>
@endif
@endsection