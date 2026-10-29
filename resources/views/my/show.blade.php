@extends('layouts.app')

@section('title', $computer->name ?? 'Төхөөрөмж')

@section('content')
@php
    $backUrl = \Illuminate\Support\Facades\Route::has('my.computers')
        ? route('my.computers')
        : (\Illuminate\Support\Facades\Route::has('my.computers.index') ? route('my.computers.index') : url()->previous());

    $warranty = $computer->warrantyState();

    // Ангилалд хамааралгүй, хоосон талбарыг харуулахгүй (дэлгэц, mouse, keyboard-д CPU, RAM, диск ...) — Админы хуудастай ижил
    $relevant = \App\Enums\EquipmentCategory::specFieldsFor($computer->category);
    $specRows = fn (array $rows) => collect($rows)
        ->filter(fn ($row) => in_array($row[0], $relevant, true) || filled($row[1]))
        ->map(fn ($row) => $row[1])
        ->all();
    $repairNumber = $computer->repairQueueNumber();

    // Ажилтанд харуулах мэдээлэл. Дотоод "Тэмдэглэл", үнийг санаатайгаар оруулаагүй.
    $sections = [
        'Үндсэн мэдээлэл' => array_filter([
            'Бүртгэлийн дугаар' => $computer->inventory_number,
            'Нэр' => $computer->name,
            'Ангилал' => $computer->category?->label(),
            'Төрөл' => $computer->type?->label(),
            'Төлөв' => $computer->status?->label(),
            'Засварын дараалал' => $repairNumber ? '#'.$repairNumber.' · '.($computer->repair_stage?->label() ?? 'Дараалалд') : null,
        ], fn ($v, $k) => $k !== 'Засварын дараалал' || $v !== null, ARRAY_FILTER_USE_BOTH),
        'Техникийн үзүүлэлт' => $specRows([
            'Үйлдвэрлэгч' => ['brand', $computer->brand],
            'Загвар' => ['model', $computer->model],
            'Серийн дугаар' => ['serial_number', $computer->serial_number],
            'Процессор (CPU)' => ['cpu', $computer->cpu],
            'RAM' => ['ram_gb', $computer->ramLabel()],
            'Санах ой (диск)' => ['storage', \App\Support\SpecSuggestions::storageLabel($computer->storage)],
            'Үйлдлийн систем' => ['os', $computer->os],
            'Үзүүлэлт' => ['specs', $computer->specs],
        ]),
        // IP, MAC: зөвхөн бөглөгдсөн бол (дэлгэц, mouse-д байхгүй)
        'Сүлжээ' => array_filter([
            'IP хаяг' => $computer->ip_address,
            'MAC хаяг' => $computer->mac_address,
        ]),
        'Байршил' => [
            'Алба хэлтэс' => $computer->department,
            'Байршил' => $computer->location,
        ],
        'Худалдан авалт ба баталгаа' => [
            'Худалдан авсан огноо' => $computer->purchase_date?->format('Y-m-d'),
            'Баталгаа дуусах огноо' => $computer->warranty_until?->format('Y-m-d'),
        ],
    ];
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-700">← Миний төхөөрөмжүүд</a>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-xl font-bold">{{ $computer->name ?? ('Компьютер #'.$computer->id) }}</h1>

        <div class="flex flex-wrap gap-2">
            @if ($warranty === 'expired')
                <span class="rounded-full bg-rose-50 px-3 py-1 text-xs text-rose-700">Баталгаа дууссан</span>
            @elseif ($warranty === 'expiring')
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-700">Баталгаа удахгүй дуусна</span>
            @elseif ($warranty === 'valid')
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700">Баталгаатай</span>
            @endif

            @if ($computer->acknowledged_at)
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs text-emerald-700">
                    Хүлээн авсан · {{ \Illuminate\Support\Carbon::parse($computer->acknowledged_at)->format('Y-m-d H:i') }}
                </span>
            @endif
        </div>
    </div>

    @unless ($computer->acknowledged_at)
        <form method="POST" action="{{ route('my.computers.acknowledge', $computer->id) }}"
              class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
            @csrf
            <p class="mb-3">Энэ төхөөрөмжийг хүлээн авсан бол баталгаажуулна уу.</p>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Хүлээн авснаа баталгаажуулах
            </button>
        </form>
    @endunless

    @foreach (array_filter($sections) as $title => $rows)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">{{ $title }}</h2>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                @foreach ($rows as $label => $value)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endforeach
</div>
@endsection