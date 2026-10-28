@extends('layouts.app')

@section('title', 'QR уншуулах')

@section('content')
<div class="mx-auto max-w-xl space-y-4">
    <div>
        <h1 class="text-xl font-bold">QR уншуулах</h1>
    </div>

    @if (session('error'))
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- QR шошгын зургаас унших --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-2 text-sm font-semibold text-slate-700">QR шошгын зураг уншуулах</div>
        <input type="file" id="qr-file" accept="image/*" capture="environment"
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm">
        <p id="scan-status" class="mt-3 text-sm text-slate-500"></p>
        <div id="file-reader" class="hidden"></div>
    </div>

    {{-- Бүртгэлийн дугаараар нээх --}}
    <form method="GET" action="{{ route('computers.resolve') }}"
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-2 text-sm font-semibold text-slate-700">Бүртгэлийн дугаараар нээх</div>
        <div class="flex gap-2">
            <input type="text" name="code" required placeholder="QR дугаар (4829135702) эсвэл бүртгэлийн дугаар"
                   class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm hover:bg-slate-100">Нээх</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const resolveUrl = @json(route('computers.resolve'));
    const statusEl = document.getElementById('scan-status');
    let handled = false;

    function go(text) {
        if (handled) return;
        handled = true;
        statusEl.textContent = 'Уншлаа, нээж байна…';
        window.location.href = resolveUrl + '?code=' + encodeURIComponent(text);
    }

    document.getElementById('qr-file').addEventListener('change', async (ev) => {
        const file = ev.target.files[0];
        if (!file) return;

        if (typeof Html5Qrcode === 'undefined') {
            statusEl.textContent = 'QR уншигч ачаалагдсангүй (интернет холболтоо шалгана уу). Бүртгэлийн дугаараар нээнэ үү.';
            return;
        }

        statusEl.textContent = 'Зургийг уншиж байна…';
        try {
            const text = await new Html5Qrcode('file-reader').scanFile(file, false);
            go(text);
        } catch (e) {
            statusEl.textContent = 'Зураг дээрээс QR код олдсонгүй. Ойроос, тод, шулуун авч дахин оролдоно уу.';
        }
    });
})();
</script>
@endsection