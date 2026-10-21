@extends('layouts.app')

@section('title', 'Миний төхөөрөмж')

@section('content')
@php
    // Ангиллын дараалал: enum-ийн дарааллаар, "Ангилаагүй" хамгийн сүүлд
    $order = array_merge(array_map(fn ($c) => $c->value, \App\Enums\EquipmentCategory::cases()), ['none']);
    $labelOf = fn ($key) => \App\Enums\EquipmentCategory::tryFrom($key)?->label() ?? 'Ангилаагүй';
    $iconOf = fn ($key) => \App\Enums\EquipmentCategory::tryFrom($key)?->icon() ?? 'M4 4h16v16H4zM9 9h6v6H9z';
    $total = $counts->sum();
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <h1 class="text-xl font-bold">Миний төхөөрөмжүүд</h1>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Ангиллаар шүүх --}}
    @if ($total > 0)
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('my.computers') }}"
               class="rounded-full px-4 py-1.5 text-sm font-medium {{ $selected === null ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                Бүгд · {{ $total }}
            </a>
            @foreach ($order as $key)
                @continue(! $counts->has($key))
                <a href="{{ route('my.computers', ['category' => $key]) }}"
                   class="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium {{ $selected === $key ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                    {{ $labelOf($key) }} · {{ $counts[$key] }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($computers->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-500">
            Танд одоогоор хариуцуулсан төхөөрөмж байхгүй байна.
        </div>
    @else
        @foreach ($order as $key)
            @continue(! $grouped->has($key))

            <section class="space-y-3">
                <div class="flex items-center gap-2 pt-2 text-sm font-semibold text-slate-700">
                    <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconOf($key) }}"/></svg>
                    {{ $labelOf($key) }}
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $grouped[$key]->count() }}</span>
                </div>

                @foreach ($grouped[$key] as $computer)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $computer->name ?? ('Компьютер #' . $computer->id) }}</div>
                                <div class="text-sm text-slate-500">Сериал: {{ $computer->serial_number ?? '-' }}</div>
                                <a href="{{ route('my.computers.show', $computer->id) }}"
                                   class="mt-2 inline-block text-sm font-medium text-blue-600 hover:underline">
                                    Үзүүлэлт харах →
                                </a>
                            </div>

                            @if ($computer->acknowledged_at)
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700">
                                    Хүлээн авсан · {{ \Illuminate\Support\Carbon::parse($computer->acknowledged_at)->format('Y-m-d H:i') }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('my.computers.acknowledge', $computer->id) }}">
                                    @csrf
                                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                        Хүлээн авснаа баталгаажуулах
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Өмнө илгээсэн, шийдвэрлэгдээгүй хүсэлт (шилжүүлэх эрх одоо зөвхөн Админд) --}}
                        @if (in_array($computer->id, $pendingIds))
                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    <b>IT-д хүлээлгэн өгөх хүсэлт илгээсэн.</b>
                                    Төхөөрөмжөө IT Админд авчирч өгнө үү — IT хүлээн авсны дараа дараагийн ажилтанд олгоно.
                                    @if ($openTransfers[$computer->id]->toUser ?? null)
                                        <div class="mt-0.5">Санал болгосон: {{ $openTransfers[$computer->id]->toUser->name }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach
    @endif
</div>
@endsection