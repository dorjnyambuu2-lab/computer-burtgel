{{--
    Төхөөрөмжийн мэдээллийн хуудас (A4) — хэвлэх эсвэл хөтчийн «Save as PDF»-ээр PDF болгох.
    Шаардлагатай: $computer, $sections, $cost, $kitItems, $events, $qrSvg (ComputerToolsController::print)
--}}
@php
    $money = fn ($v) => \App\Support\Money::format($v);
    $holder = trim($computer->holder_surname.' '.$computer->holder_name) ?: ($computer->assignedUser?->name ?? $computer->assigned_to);
@endphp
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $computer->inventory_number }} · {{ $computer->name }}</title>
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #0f172a; font: 12px/1.45 "Segoe UI", Roboto, Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #0f172a; }
        .toolbar button, .toolbar a { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 16px; border: 0; border-radius: 8px;
            font: 600 13px "Segoe UI", Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar .primary { background: #2563eb; color: #fff; }
        .toolbar .ghost { background: #1e293b; color: #e2e8f0; }
        .page { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm; background: #fff; box-shadow: 0 4px 24px rgba(15, 23, 42, .15); }

        header { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid #0f172a; }
        .doc-title { margin: 0; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
        h1 { margin: 4px 0 6px; font-size: 22px; line-height: 1.2; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { padding: 2px 8px; border: 1px solid #cbd5e1; border-radius: 999px; font-size: 11px; }
        .chip.mono { font-family: Consolas, ui-monospace, monospace; }
        .qr { width: 92px; flex-shrink: 0; text-align: center; font: 10px Consolas, monospace; color: #475569; }
        .qr svg { display: block; width: 92px; height: 92px; }

        h2 { margin: 18px 0 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        .kv { table-layout: fixed; }
        .kv td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; word-break: break-word; }
        .kv td.k { width: 18%; background: #f8fafc; color: #64748b; }
        .kv td.empty { color: #cbd5e1; }
        .list th, .list td { padding: 5px 8px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        .list th { background: #f8fafc; font-size: 10px; font-weight: 600; text-transform: uppercase; color: #64748b; }
        .num { text-align: right !important; white-space: nowrap; }
        .total td { font-weight: 700; background: #eff6ff; }
        .notes { padding: 8px; border: 1px solid #e2e8f0; white-space: pre-line; }

        .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 28px; page-break-inside: avoid; }
        .sign .line { height: 32px; border-bottom: 1px solid #0f172a; }
        .sign .cap { margin-top: 4px; font-size: 11px; color: #475569; }
        footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; display: flex; justify-content: space-between; }
        section { page-break-inside: avoid; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">🖨 Хэвлэх / PDF хадгалах</button>
        <a href="{{ route('computers.show', $computer) }}" class="ghost">← Буцах</a>
    </div>

    <div class="page">
        <header>
            <div>
                <p class="doc-title">Төхөөрөмжийн мэдээллийн хуудас · {{ config('app.name') }}</p>
                <h1>{{ $computer->name }}</h1>
                <div class="meta">
                    <span class="chip mono">{{ $computer->inventory_number }}</span>
                    <span class="chip">{{ $computer->sequenceLabel() }}</span>
                    @if ($computer->status)<span class="chip">{{ $computer->status->label() }}</span>@endif
                    @if ($computer->category)<span class="chip">{{ $computer->category->label() }}</span>@endif
                </div>
            </div>
            <div class="qr">{!! $qrSvg !!}{{ $computer->qr_code }}</div>
        </header>

        {{-- Мэдээллийн хэсгүүд: мөр бүрт 2 талбар (шошго | утга | шошго | утга) --}}
        @foreach (array_filter($sections) as $title => $rows)
            <section>
                <h2>{{ $title }}</h2>
                <table class="kv">
                    @foreach (array_chunk($rows, 2, true) as $pair)
                        <tr>
                            @foreach ($pair as $label => $value)
                                <td class="k">{{ $label }}</td>
                                <td class="{{ filled($value) ? '' : 'empty' }}">{{ filled($value) ? $value : '—' }}</td>
                            @endforeach
                            @if (count($pair) === 1)<td class="k"></td><td></td>@endif
                        </tr>
                    @endforeach
                </table>
            </section>
        @endforeach

        <section>
            <h2>Үнэ ба зардал</h2>
            <table class="list">
                <tr><td>Худалдан авсан үнэ</td><td class="num">{{ $money($cost['price']) }}</td></tr>
                <tr><td>Засварын зардал ({{ $cost['repair_count'] }} удаа засварт орсон)</td><td class="num">{{ $money($cost['repair_cost']) }}</td></tr>
                <tr class="total"><td>Нийт өртөг (үнэ + засвар)</td><td class="num">{{ $money($cost['total']) }}</td></tr>
            </table>
        </section>

        @if ($kitItems->count() > 1)
            <section>
                <h2>Иж бүрдэл · {{ $kitItems->count() }} төхөөрөмж</h2>
                <table class="list">
                    <tr><th>Бүртгэлийн дугаар</th><th>Ангилал</th><th>Нэр</th><th>Серийн дугаар</th><th>Төлөв</th></tr>
                    @foreach ($kitItems as $item)
                        <tr @if ($item->id === $computer->id) style="font-weight:600" @endif>
                            <td style="font-family:Consolas,monospace">{{ $item->inventory_number }}</td>
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
                <h2>Түүх (сүүлийн {{ $events->count() }})</h2>
                <table class="list">
                    <tr><th style="width:18%">Огноо</th><th style="width:16%">Үйлдэл</th><th>Тайлбар</th><th class="num">Зардал</th><th style="width:16%">Хэн</th></tr>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ $event->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $event->label() }}</td>
                            <td>{{ collect([$event->user?->name, $event->note])->filter()->implode(' · ') ?: '—' }}</td>
                            <td class="num">{{ $event->cost ? $money($event->cost) : '' }}</td>
                            <td>{{ $event->actor?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        @endif

        @if (filled($computer->notes))
            <section>
                <h2>Тэмдэглэл</h2>
                <div class="notes">{{ $computer->notes }}</div>
            </section>
        @endif

        {{-- Гарын үсэг: хүлээлгэн өгсөн (IT) / хүлээн авсан (хариуцагч) --}}
        <div class="signs">
            <div class="sign"><div class="line"></div><div class="cap">Хүлээлгэн өгсөн: {{ auth()->user()->name }} &nbsp; /гарын үсэг/</div></div>
            <div class="sign"><div class="line"></div><div class="cap">Хүлээн авсан: {{ $holder ?: '........................' }} &nbsp; /гарын үсэг/</div></div>
        </div>

        <footer>
            <span>Хэвлэсэн: {{ auth()->user()->name }} · {{ now()->format('Y-m-d H:i') }}</span>
            <span>{{ $computer->inventory_number }}</span>
        </footer>
    </div>
</body>
</html>
