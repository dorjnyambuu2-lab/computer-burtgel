@extends('layouts.app')

@section('title', 'QR уншуулах')

@section('content')
<div class="mx-auto max-w-xl space-y-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">QR уншуулах</h1>
    </div>

    @if (session('error'))
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- Камераар шууд уншуулах (камер зөвхөн HTTPS эсвэл localhost дээр ажиллана) --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="text-sm font-semibold text-slate-700">Камераар уншуулах</div>
            <button type="button" id="cam-toggle"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-semibold !text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.66-.9l.82-1.2A2 2 0 0 1 10.07 4h3.86a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                <span id="cam-toggle-label">Камер асаах</span>
            </button>
        </div>
        <div id="cam-reader" class="mt-3 hidden overflow-hidden rounded-xl bg-black"></div>
        <p id="cam-status" class="mt-3 text-sm text-slate-500">QR шошгоо камерт харуулахад төхөөрөмжийн хуудас автоматаар нээгдэнэ.</p>
    </div>

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
    const camBtn = document.getElementById('cam-toggle');
    const camLabel = document.getElementById('cam-toggle-label');
    const camBox = document.getElementById('cam-reader');
    const camStatus = document.getElementById('cam-status');
    const camHint = camStatus.textContent;
    const libMissing = 'QR уншигч ачаалагдсангүй (интернет холболтоо шалгана уу). Бүртгэлийн дугаараар нээнэ үү.';
    let handled = false;

    function go(text, el) {
        if (handled) return;
        handled = true;
        el.textContent = 'Уншлаа, нээж байна…';
        window.location.href = resolveUrl + '?code=' + encodeURIComponent(text);
    }

    // ---------- Камераар шууд уншуулах ----------
    let cam = null, camOn = false, camBusy = false;

    function setCamUi(on) {
        camOn = on;
        camBox.classList.toggle('hidden', !on);
        camLabel.textContent = on ? 'Зогсоох' : 'Камер асаах';
    }

    function camError(e) {
        const s = String((e && (e.name || e.message)) || e || '');
        if (/NotAllowed|Permission/i.test(s)) return 'Камерын зөвшөөрөл олгогдсонгүй. Хөтчийн хаягийн мөрөн дэх камерын тэмдгээс зөвшөөрөөд дахин оролдоно уу.';
        if (/NotFound|not found|Overconstrained/i.test(s)) return 'Энэ төхөөрөмжид камер олдсонгүй. Доорх аргуудаар уншуулна уу.';
        if (/NotReadable|Could not start|in use/i.test(s)) return 'Камерыг өөр програм ашиглаж байна. Тэр програмаа хаагаад дахин оролдоно уу.';
        return 'Камер асаахад алдаа гарлаа. Доорх аргуудаар уншуулна уу.';
    }

    async function startCam() {
        if (camOn || camBusy || handled) return;
        if (!window.isSecureContext || !navigator.mediaDevices) {
            camStatus.textContent = 'Камер зөвхөн HTTPS холболтоор ажиллана. Доорх «QR шошгын зураг уншуулах» хэсгээр утасны камераар зураг авч уншуулна уу.';
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            camStatus.textContent = libMissing;
            return;
        }

        camBusy = true;
        camBtn.disabled = true;
        camStatus.textContent = 'Камер асааж байна…';
        camBox.classList.remove('hidden');
        cam = cam || new Html5Qrcode('cam-reader', { verbose: false });
        try {
            await cam.start(
                { facingMode: 'environment' }, // утсан дээр арын камер, компьютер дээр байгаа камер
                {
                    fps: 10,
                    qrbox: (w, h) => {
                        const s = Math.max(120, Math.floor(Math.min(w, h) * 0.7));
                        return { width: s, height: s };
                    },
                },
                (text) => {
                    try { cam.pause(true); } catch (e) {}
                    go(text, camStatus);
                },
                () => {} // кадр бүрт QR олдоогүй үед — чимээгүй
            );
            setCamUi(true);
            camStatus.textContent = 'QR шошгоо хүрээн дотор барина уу.';
        } catch (e) {
            setCamUi(false);
            camStatus.textContent = camError(e);
        } finally {
            camBusy = false;
            camBtn.disabled = false;
        }
    }

    async function stopCam() {
        if (!camOn || camBusy) return;
        camBusy = true;
        try { await cam.stop(); } catch (e) {}
        camBusy = false;
        setCamUi(false);
        camStatus.textContent = camHint;
    }

    camBtn.addEventListener('click', () => (camOn ? stopCam() : startCam()));

    // Камерын зөвшөөрлийг өмнө нь өгсөн бол хуудас нээгдэхэд шууд асаана
    function autoStart() {
        if (!window.isSecureContext || !navigator.permissions) return;
        navigator.permissions.query({ name: 'camera' })
            .then((p) => { if (p.state === 'granted') startCam(); })
            .catch(() => {}); // Firefox зэрэг 'camera' асуулгыг дэмждэггүй хөтөч — товчоор асаана
    }
    autoStart();

    // Хуудаснаас гарахад камерыг унтраана; «Буцах» дарж кэшээс сэргэвэл дахин уншихад бэлэн болгоно
    window.addEventListener('pagehide', () => {
        if (camOn && cam) { cam.stop().catch(() => {}); setCamUi(false); }
    });
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        handled = false;
        statusEl.textContent = '';
        camStatus.textContent = camHint;
        autoStart();
    });

    // ---------- QR шошгын зургаас унших ----------
    document.getElementById('qr-file').addEventListener('change', async (ev) => {
        const file = ev.target.files[0];
        if (!file) return;

        if (typeof Html5Qrcode === 'undefined') {
            statusEl.textContent = libMissing;
            return;
        }

        statusEl.textContent = 'Зургийг уншиж байна…';
        try {
            const text = await new Html5Qrcode('file-reader').scanFile(file, false);
            go(text, statusEl);
        } catch (e) {
            statusEl.textContent = 'Зураг дээрээс QR код олдсонгүй. Ойроос, тод, шулуун авч дахин оролдоно уу.';
        }
    });
})();
</script>
@endsection
