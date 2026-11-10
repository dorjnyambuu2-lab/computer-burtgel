<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>Өгөгдлийн сан ажиллахгүй байна · {{ config('app.name') }}</title>
    {{-- Өгөгдлийн сангүй үед ч харагдах ёстой тул CSS-ийг энд шууд бичив (Vite, сесс, DB-д хамааралгүй) --}}
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; color: #0f172a;
            font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px; box-sizing: border-box; }
        .card { max-width: 480px; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 32px;
            box-shadow: 0 10px 30px rgb(15 23 42 / .08); }
        .icon { width: 48px; height: 48px; border-radius: 14px; background: #fef3c7; color: #b45309; display: grid; place-items: center; }
        h1 { font-size: 20px; margin: 18px 0 8px; }
        p { color: #475569; line-height: 1.6; margin: 0 0 10px; font-size: 15px; }
        .hint { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; font-size: 14px; color: #334155; }
        a { display: inline-block; margin-top: 18px; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 12px; font-weight: 600; font-size: 14px; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; } .card { background: #111a2e; border-color: #1e293b; }
            p { color: #94a3b8; } .hint { background: #0b1220; border-color: #1e293b; color: #cbd5e1; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3 1 0 2-.1 2.9-.2M18 16v3M18 22h.01"/></svg>
    </div>
    <h1>Өгөгдлийн сан түр ажиллахгүй байна</h1>
    <p>Систем өгөгдлийн сантай (MySQL) холбогдож чадсангүй. Таны өгөгдөл хадгалагдсан хэвээр байна.</p>
    <div class="hint">IT ажилтан: XAMPP Control Panel дээр <b>MySQL</b>-ийг асааж (Start) шалгана уу. Хуудас 15 секунд тутам өөрөө дахин оролдоно.</div>
    <a href="{{ request()->fullUrl() }}">Дахин оролдох</a>
</div>
</body>
</html>
