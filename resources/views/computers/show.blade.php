@extends('layouts.app')

@section('title', $computer->name ?? ('Компьютер #'.$computer->id))

@section('content')
@php
    $statusLabel = $computer->status?->label();
    $statusClass = match ($statusLabel) {
        'Ашиглагдаж байгаа' => 'bg-emerald-50 text-emerald-700',
        'Засварт' => 'bg-amber-50 text-amber-700',
        'Агуулахад' => 'bg-sky-50 text-sky-700',
        'Шинэ' => 'bg-violet-50 text-violet-700',
        default => 'bg-slate-100 text-slate-600',
    };

    $warranty = $computer->warrantyState();
    $holderName = $computer->assignedUser?->name ?? $computer->assigned_to;

    $acknowledgement = null;
    if ($computer->assigned_user_id) {
        $acknowledgement = $computer->acknowledged_at
            ? 'Хүлээн авсан · '.\Illuminate\Support\Carbon::parse($computer->acknowledged_at)->format('Y-m-d H:i')
            : 'Баталгаажуулаагүй (хүлээгдэж байна)';
    }

    // Ангилалд хамааралгүй, хоосон талбарыг харуулахгүй (жишээ нь mouse-ийн CPU, RAM)
    $relevant = \App\Enums\EquipmentCategory::specFieldsFor($computer->category);
    $specRows = fn (array $rows) => collect($rows)
        ->filter(fn ($row) => in_array($row[0], $relevant, true) || filled($row[1]))
        ->map(fn ($row) => $row[1])
        ->all();

    // «Буцах»: жагсаалтаас (шүүлтүүртэй) ирсэн бол тэр хуудас руу нь
    $previous = url()->previous();
    $backUrl = parse_url($previous, PHP_URL_PATH) === parse_url(route('computers.index'), PHP_URL_PATH) ? $previous : route('computers.index');

    $sections = [
        'Үндсэн мэдээлэл' => [
            'Дэс дугаар' => $computer->sequenceLabel(),
            'Бүртгэлийн дугаар' => $computer->inventory_number,
            'QR дугаар' => $computer->qr_code,
            'Нэр' => $computer->name,
            'Ангилал' => $computer->category?->label(),
            'Төрөл' => $computer->type?->label(),
            'Төлөв' => $statusLabel,
        ],
        'Техникийн үзүүлэлт' => $specRows([
            'Үйлдвэрлэгч' => ['brand', $computer->brand],
            'Загвар' => ['model', $computer->model],
            'Серийн дугаар' => ['serial_number', $computer->serial_number],
            'Процессор (CPU)' => ['cpu', $computer->cpu],
            'RAM' => ['ram_gb', $computer->ramLabel()],
            'Санах ой (диск)' => ['storage', $computer->storage],
            'Үйлдлийн систем' => ['os', $computer->os],
            'Үзүүлэлт' => ['specs', $computer->specs],
        ]),
        'Сүлжээ' => $specRows([
            'IP хаяг' => ['ip_address', $computer->ip_address],
            'MAC хаяг' => ['mac_address', $computer->mac_address],
            // Ажилтан өөрийн компьютероос нэвтрэхэд IP, MAC автоматаар шинэчлэгдсэн огноо
            'Автоматаар шинэчилсэн' => ['ip_address', $computer->network_updated_at?->format('Y-m-d H:i')],
        ]),
        'Байршил ба хариуцагч' => [
            'Салбар' => $computer->branch?->name,
            'Нэгж' => $computer->unit,
            'Алба' => $computer->department,
            'Албан тушаал' => $computer->position,
            'Байршил' => $computer->location,
            'Хариуцагч' => $holderName,
            'Овог нэр' => trim($computer->holder_surname.' '.$computer->holder_name) ?: null,
            'Хүлээн авсан баталгаа' => $acknowledgement,
        ],
        'Худалдан авалт ба баталгаа' => [
            'Худалдан авсан огноо' => $computer->purchase_date?->format('Y-m-d'),
            'Баталгаа дуусах огноо' => $computer->warranty_until?->format('Y-m-d'),
        ],
    ];

    // Зардлын тооцоо: үнэ + засварын нийт зардал = нийт өртөг
    $cost = $computer->costSummary();
    $money = fn ($v) => \App\Support\Money::format($v);
@endphp

<div class="mx-auto max-w-6xl space-y-4">
    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1 text-sm font-medium !text-slate-500 hover:!text-slate-800">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Жагсаалт руу буцах
    </a>

    {{-- Толгой хэсэг: ангиллын икон, нэр, төлөв, үйлдлүүд --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex min-w-0 items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ ($computer->category ?? \App\Enums\EquipmentCategory::Processor)->icon() }}"/></svg>
            </div>
            <div class="min-w-0">
                <h1 class="!mb-0 truncate text-xl font-bold text-slate-900"><span class="font-medium text-slate-400">{{ $computer->sequenceLabel() }}</span> {{ $computer->name ?? ('Компьютер #'.$computer->id) }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    @if ($computer->inventory_number)
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{{ $computer->inventory_number }}</span>
                    @endif
                    @if ($statusLabel)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $statusLabel }}</span>
                    @endif
                    @if ($warranty === 'expired')
                        <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700">Баталгаа дууссан</span>
                    @elseif ($warranty === 'expiring')
                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Баталгаа удахгүй дуусна</span>
                    @elseif ($warranty === 'valid')
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Баталгаатай</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('computers.label', $computer) }}"
               class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium !text-slate-700 transition hover:bg-slate-50">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2"/></svg>
                QR
            </a>
            <a href="{{ route('computers.edit', $computer) }}"
               class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-semibold !text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
                Засах
            </a>
            {{-- Иж бүрдлийн үндсэн компьютер бол дагалдах хэрэгслүүд хамт устгагдана (Computer::booted) --}}
            @php $kitCount = $computer->kit_parent_id ? 0 : $computer->kitItems()->count(); @endphp
            <form method="POST" action="{{ route('computers.destroy', $computer) }}"
                  onsubmit="return confirm(@js('Энэ төхөөрөмжийг устгах уу?'.($kitCount ? ' Иж бүрдлийн '.$kitCount.' дагалдах хэрэгсэл (дэлгэц, mouse ...) хамт устгагдана.' : '').' (Устгасан хэсгээс сэргээх боломжтой)'))">
                @csrf
                @method('DELETE')
                <button title="Устгах" aria-label="Устгах"
                        class="inline-flex h-10 w-10 !min-h-0 items-center justify-center !rounded-lg !border !border-slate-200 !bg-white !p-0 !text-slate-500 transition hover:!border-rose-200 hover:!bg-rose-50 hover:!text-rose-600">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Утсан дээр үйлдлүүд эхэнд, том дэлгэц дээр баруун талд --}}
        <div class="order-first lg:order-none lg:col-span-1 lg:col-start-3 lg:row-start-1">
            @include('computers._actions')
        </div>

        {{-- Мэдээлэл --}}
        <div class="space-y-4 lg:col-span-2 lg:col-start-1 lg:row-start-1">
            @foreach (array_filter($sections) as $title => $rows)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <h2 class="!mb-0 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $title }}</h2>
                    <dl class="grid gap-x-8 gap-y-4 p-5 sm:grid-cols-2">
                        @foreach ($rows as $label => $value)
                            <div class="min-w-0">
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 break-words text-sm font-medium {{ filled($value) ? 'text-slate-900' : 'text-slate-300' }}">{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach

            {{-- Иж бүрдэл: үндсэн компьютер (PC-0012) ба дагалдах хэрэгслүүд (PC-0012-1, PC-0012-2 ...) --}}
            @php
                $kitMain = $computer->kit_parent_id ? $computer->kitParent : ($computer->canBeKitMain() ? $computer : null);
                $kitItems = $kitMain ? $kitMain->kitItems()->get() : collect();
            @endphp
            @if ($kitMain)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                        <h2 class="!mb-0 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Иж бүрдэл <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 font-mono normal-case tracking-normal text-slate-800">{{ $kitMain->kitBase() }}</span>
                            <span class="font-normal normal-case tracking-normal text-slate-400">· {{ $kitItems->count() + 1 }} төхөөрөмж</span>
                        </h2>
                        @unless ($kitMain->trashed())
                            <a href="{{ route('computers.create', ['kit' => $kitMain->id]) }}" class="text-xs font-semibold !text-blue-600 hover:underline">+ Дагалдах хэрэгсэл нэмэх</a>
                        @endunless
                    </div>
                    <ul class="divide-y divide-slate-100 px-5 text-sm">
                        @foreach ($kitItems->prepend($kitMain) as $item)
                            @php $current = $item->id === $computer->id; @endphp
                            <li class="flex items-center gap-3 py-2 {{ $current ? 'font-semibold' : '' }}">
                                <span class="w-28 shrink-0 font-mono text-xs {{ $item->kit_parent_id ? 'text-slate-500' : 'text-slate-800' }}">{{ $item->inventory_number }}</span>
                                <span class="w-24 shrink-0 text-xs text-slate-500">{{ $item->category?->label() ?? $item->type?->label() }}</span>
                                @if ($current || $item->trashed())
                                    <span class="min-w-0 flex-1 truncate">{{ $item->name }}@if ($item->trashed()) <span class="text-xs font-normal text-rose-600">(устгасан)</span>@endif</span>
                                @else
                                    <a href="{{ route('computers.show', $item) }}" class="min-w-0 flex-1 truncate !text-blue-700 hover:underline">{{ $item->name }}</a>
                                @endif
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $item->status->badgeClasses() }}">{{ $item->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($kitItems->count() === 1)
                        <p class="px-5 pb-4 text-xs text-slate-400">Дэлгэц, mouse, keyboard зэргийг энэ компьютерийн иж бүрдэлд нэмбэл {{ $kitMain->kitBase() }}-2, -3 ... дугаар авна (компьютер өөрөө {{ $kitMain->kitBase() }}-1).</p>
                    @endif
                </div>
            @endif

            {{-- Зардлын тооцоо --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                    <h2 class="!mb-0 text-xs font-semibold uppercase tracking-wide text-slate-500">Зардлын тооцоо</h2>
                    @if ($cost['price'] === null)
                        <a href="{{ route('computers.edit', $computer) }}" class="text-xs !text-blue-600 hover:underline">Үнэ оруулах →</a>
                    @endif
                </div>
                <div class="p-5">
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <dt class="text-xs text-slate-500"> Үнэ</dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-800">{{ $money($cost['price']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-4 py-3">
                        <dt class="text-xs text-amber-700">Засварын зардал <span class="text-amber-600/80">· {{ $cost['repair_count'] }} удаа засварт орсон</span></dt>
                        <dd class="mt-1 text-lg font-semibold text-amber-900">{{ $money($cost['repair_cost']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-blue-50 px-4 py-3">
                        <dt class="text-xs text-blue-700">Нийт өртөг (үнэ + засвар)</dt>
                        <dd class="mt-1 text-lg font-bold text-blue-900">{{ $money($cost['total']) }}</dd>
                    </div>
                </dl>
                @if ($cost['price'] && $cost['repair_cost'] > 0)
                    @php $ratio = round($cost['repair_cost'] / $cost['price'] * 100); @endphp
                    <p class="mt-3 text-xs {{ $ratio >= 50 ? 'text-rose-600' : 'text-slate-500' }}">
                        Засварын зардал үнийн <b>{{ $ratio }}%</b>-тай тэнцэж байна.
                        @if ($ratio >= 50) Шинээр авах нь хэмнэлттэй байж магадгүй. @endif
                    </p>
                @endif
                </div>
            </div>

            @if (filled($computer->notes))
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <h2 class="!mb-0 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Тэмдэглэл</h2>
                    <p class="whitespace-pre-line p-5 text-sm text-slate-700">{{ $computer->notes }}</p>
                </div>
            @endif

            <p class="px-1 text-xs text-slate-400">
                Бүртгэсэн: {{ $computer->created_at?->format('Y-m-d H:i') }}
                · Сүүлд шинэчилсэн: {{ $computer->updated_at?->format('Y-m-d H:i') }}
            </p>
        </div>
    </div>
</div>
@endsection