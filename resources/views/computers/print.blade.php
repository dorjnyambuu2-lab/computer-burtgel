{{--
    Төхөөрөмжийн мэдээллийн хуудас — MNS 5140:2011 «Албан бичиг»-ийн загвараар:
    A4, захын зай дээд 20, доод 20, зүүн 30 (хавтаслах), баруун 15 мм; Times New Roman 12pt; байгууллагын нэр,
    огноо, дугаар, баримтын нэр, гарын үсэг (албан тушаал, гарын үсэг, нэр), хуудасны дугаар.
    Хэвлэх эсвэл хөтчийн «Save as PDF»-ээр PDF болно.
    Шаардлагатай: $computer, $sections, $cost, $kitItems, $events, $qrSvg (ComputerToolsController::print)
--}}
@php
    $money = fn ($v) => \App\Support\Money::format($v);
    $holder = trim($computer->holder_surname.' '.$computer->holder_name) ?: ($computer->assignedUser?->name ?? $computer->assigned_to);
    $me = auth()->user();
    $organization = config('app.organization') ?: ($computer->branch?->name ?? config('app.name'));
    $unit = config('app.organization') ? $computer->branch?->name : null;
    $n = 0; // хэсгийн дугаар
@endphp
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $computer->inventory_number }} · {{ $computer->name }}</title>
    <style>
        /* MNS 5140:2011: дээд 20, баруун 15, доод 20, зүүн 30 мм */
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 30mm;
            @bottom-center { content: counter(page) " / " counter(pages); font: 10pt "Times New Roman", serif; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #d4d4d8; color: #000; font: 12pt/1.3 "Times New Roman", Times, serif; }

        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #0f172a; }
        .toolbar button, .toolbar a { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 16px; border: 0; border-radius: 8px;
            font: 600 13px "Segoe UI", Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .ghost { background: #1e293b; color: #e2e8f0; }
        /* Дэлгэц дээр A4 цаас шиг (захын зай стандартын дагуу) */
        .page { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 20mm 15mm 20mm 30mm; background: #fff; box-shadow: 0 4px 24px rgba(0, 0, 0, .2); }

        /* Толгой: байгууллага (зүүн), QR (баруун) */
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12mm; }
        .org { font-weight: bold; text-transform: uppercase; line-height: 1.25; }
        .org small { display: block; margin-top: 2px; font-weight: normal; text-transform: none; font-size: 11pt; }
        .qr { width: 24mm; text-align: center; font: 8pt "Courier New", monospace; }
        .qr svg { display: block; width: 24mm; height: 24mm; }
        /* Огноо, дугаар, газар */
        .reg { display: flex; justify-content: space-between; margin-top: 6mm; font-size: 11pt; }
        .place { margin-top: 1mm; font-size: 11pt; }
        /* Баримтын нэр */
        .title { margin: 8mm 0 2mm; text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: .02em; }
        .subtitle { margin: 0 0 6mm; text-align: center; }

        h2 { margin: 6mm 0 2mm; font-size: 12pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 11pt; }
        td, th { padding: 1.5mm 2mm; border: .5pt solid #000; vertical-align: top; text-align: left; word-break: break-word; }
        th { font-weight: bold; text-align: center; }
        .kv { table-layout: fixed; }
        .kv td.k { width: 20%; font-weight: bold; }
        .num { text-align: right !important; white-space: nowrap; }
        .total td { font-weight: bold; }
        .notes { padding: 2mm; border: .5pt solid #000; white-space: pre-line; font-size: 11pt; }
        section { page-break-inside: avoid; }

        /* Гарын үсэг: АЛБАН ТУШААЛ ........ (гарын үсэг) НЭР */
        .signs { margin-top: 12mm; page-break-inside: avoid; }
        .sign { display: grid; grid-template-columns: 38mm 1fr 40mm 1fr; align-items: end; gap: 3mm; margin-bottom: 8mm; }
        .sign .role { font-weight: bold; text-transform: uppercase; font-size: 11pt; }
        .sign .line { border-bottom: .5pt solid #000; height: 7mm; text-align: center; font-size: 8pt; color: #555; }
        .sign .cap { font-size: 8pt; text-align: center; color: #555; }
        .printed { margin-top: 6mm; font-size: 9pt; color: #555; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('computers.show', $computer) }}" class="ghost">← Буцах</a>
        <button type="button" class="primary" onclick="window.print()">🖨 Хэвлэх / PDF хадгалах</button>
    </div>

    <div class="page">
        <div class="head">
            <div class="org">{{ $organization }}@if ($unit)<small>{{ $unit }}</small>@endif</div>
            <div class="qr">{!! $qrSvg !!}{{ $computer->qr_code }}</div>
        </div>

        <div class="reg">
            <span>{{ now()->format('Y.m.d') }}</span>
            <span>Дугаар: {{ $computer->inventory_number }}</span>
        </div>
        @if ($computer->branch)<div class="place">{{ $computer->branch->name }}</div>@endif

        <div class="title">Төхөөрөмжийн мэдээллийн хуудас</div>
        <p class="subtitle">{{ $computer->name }}@if ($computer->category) ({{ mb_strtolower($computer->category->label()) }})@endif</p>

        {{-- Мэдээллийн хэсгүүд: мөр бүрт 2 талбар (шошго | утга | шошго | утга) --}}
        @foreach (array_filter($sections) as $title => $rows)
            <section>
                <h2>{{ ++$n }}. {{ $title }}</h2>
                <table class="kv">
                    @foreach (array_chunk($rows, 2, true) as $pair)
                        <tr>
                            @foreach ($pair as $label => $value)
                                <td class="k">{{ $label }}</td>
                                <td>{{ filled($value) ? $value : '—' }}</td>
                            @endforeach
                            @if (count($pair) === 1)<td class="k"></td><td></td>@endif
                        </tr>
                    @endforeach
                </table>
            </section>
        @endforeach

        <section>
            <h2>{{ ++$n }}. Үнэ ба зардал</h2>
            <table>
                <tr><td>Худалдан авсан үнэ</td><td class="num">{{ $money($cost['price']) }}</td></tr>
                <tr><td>Засварын зардал ({{ $cost['repair_count'] }} удаа засварт орсон)</td><td class="num">{{ $money($cost['repair_cost']) }}</td></tr>
                <tr class="total"><td>Нийт өртөг (үнэ + засвар)</td><td class="num">{{ $money($cost['total']) }}</td></tr>
            </table>
        </section>

        @if ($kitItems->count() > 1)
            <section>
                <h2>{{ ++$n }}. Иж бүрдэл ({{ $kitItems->count() }} төхөөрөмж)</h2>
                <table>
                    <tr><th style="width:8%">№</th><th>Бүртгэлийн дугаар</th><th>Ангилал</th><th>Нэр</th><th>Серийн дугаар</th><th>Төлөв</th></tr>
                    @foreach ($kitItems as $item)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{{ $item->inventory_number }}</td>
                            <td>{{ $item->category?->label() ?? $item->type?->label() }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->serial_number ?: '—' }}</td>
                            <td>{{ $item->status?->label() }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @endif

        @if ($events->isNotEmpty())
            <section>
                <h2>{{ ++$n }}. Хөдөлгөөний түүх (сүүлийн {{ $events->count() }})</h2>
                <table>
                    <tr><th style="width:8%">№</th><th style="width:20%">Огноо</th><th style="width:18%">Үйлдэл</th><th>Тайлбар</th><th style="width:14%">Зардал</th></tr>
                    @foreach ($events as $event)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{{ $event->created_at->format('Y.m.d H:i') }}</td>
                            <td>{{ $event->label() }}</td>
                            <td>{{ collect([$event->user?->name, $event->note])->filter()->implode(' · ') ?: '—' }}</td>
                            <td class="num">{{ $event->cost ? $money($event->cost) : '' }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @endif

        @if (filled($computer->notes))
            <section>
                <h2>{{ ++$n }}. Тэмдэглэл</h2>
                <div class="notes">{{ $computer->notes }}</div>
            </section>
        @endif

        {{-- Гарын үсэг (MNS 5140): АЛБАН ТУШААЛ — гарын үсэг — НЭР --}}
        <div class="signs">
            <div class="sign">
                <span class="role">Хүлээлгэн өгсөн:</span>
                <span>{{ $me->position ?: $me->roleLabel() }}</span>
                <span><span class="line">&nbsp;</span><span class="cap">(гарын үсэг)</span></span>
                <span>{{ trim($me->surname.' '.$me->name) }}</span>
            </div>
            <div class="sign">
                <span class="role">Хүлээн авсан:</span>
                <span>{{ $computer->position ?: '' }}</span>
                <span><span class="line">&nbsp;</span><span class="cap">(гарын үсэг)</span></span>
                <span>{{ $holder ?: '' }}</span>
            </div>
        </div>

        <p class="printed">Хэвлэсэн: {{ trim($me->surname.' '.$me->name) }}, {{ now()->format('Y.m.d H:i') }} · {{ config('app.name') }}</p>
    </div>
</body>
</html>
