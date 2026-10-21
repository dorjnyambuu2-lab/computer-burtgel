<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR шошго · {{ $computer->activityLabel() }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 40px 16px;
            background: #f1f5f9;
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .card {
            width: 320px;
            max-width: 100%;
            padding: 24px 24px 18px;
            text-align: center;
            background: #fff;
            border: 2px solid #1e293b;
            border-radius: 16px;
        }

        .qr svg { display: block; width: 100%; height: auto; }

        .title { margin-top: 14px; font-size: 20px; font-weight: 800; line-height: 1.25; word-break: break-word; }
        .sub { margin-top: 4px; font-size: 12px; color: #64748b; }

        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }

        .code { margin-top: 4px; font-family: ui-monospace, Consolas, monospace; font-size: 15px; font-weight: 700; letter-spacing: .12em; }
        .btn {
            display: inline-block;
            padding: 12px 22px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            color: #334155;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            cursor: pointer;
        }
        .btn:hover { filter: brightness(.96); }
        .btn-primary { color: #fff; background: #4f46e5; border-color: #4f46e5; }

        /* Хэвлэх: зөвхөн шошго, ойролцоогоор 60мм өргөнтэй */
        @page { margin: 8mm; }
        @media print {
            body { display: block; min-height: 0; padding: 0; background: #fff; }
            .actions { display: none; }
            .card { width: 60mm; padding: 4mm 4mm 3mm; border: .3mm solid #000; border-radius: 3mm; }
            .title { margin-top: 2mm; font-size: 10pt; }
            .sub { font-size: 7pt; }
            .code { font-size: 10pt; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="qr">{!! $qrSvg !!}</div>
        <div class="title">{{ $computer->activityLabel() }}</div>
        <div class="code">{{ $computer->qr_code }}</div>
        <div class="sub">{{ config('app.name') }}</div>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Хэвлэх</button>
        <a class="btn" href="{{ route('computers.qr-png', $computer) }}">PNG татах</a>
        <a class="btn" href="{{ route('computers.show', $computer) }}">Буцах</a>
    </div>
</body>
</html>