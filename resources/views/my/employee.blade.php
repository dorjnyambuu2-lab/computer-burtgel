@php
    use App\Models\AssetRequest;
    use Illuminate\Support\Facades\Schema;

    $me = auth()->user();
    $q = trim((string) request('q'));

    // Миний төхөөрөмж: хэрэглэгчийн ID-аар (нэр солигдсон ч алга болохгүй); хуучин, хэрэглэгчтэй холбогдоогүй
    // текстэн хариуцагч нь нэр / нэвтрэх нэр / имэйлээр
    $mineQuery = \App\Models\Computer::query()->where(function ($w) use ($me) {
        $w->where('assigned_user_id', $me->id)
            ->orWhere(fn ($t) => $t->whereNull('assigned_user_id')->whereIn('assigned_to', array_filter([$me->name, $me->username, $me->email])));
    });
    $assets = (clone $mineQuery)->when($q !== '', fn($x) => $x->where(fn($w) => $w
            ->where('name', 'like', "%$q%")->orWhere('serial_number', 'like', "%$q%")
            ->orWhere('brand', 'like', "%$q%")->orWhere('model', 'like', "%$q%")))
        ->orderBy('name')->get();
    $total = (clone $mineQuery)->count();
    $queueNumbers = \App\Models\Computer::repairQueueNumbers();

    $hasReq = Schema::hasTable('asset_requests');
    $openIssues = $hasReq ? AssetRequest::where('user_id', $me->id)->where('type', 'issue')->whereIn('status', ['pending', 'in_progress'])->count() : 0;
    $unack = Schema::hasColumn('computers', 'acknowledged_at') ? (clone $mineQuery)->whereNull('acknowledged_at')->count() : 0;

    $typeMap = ['desktop' => 'Суурин компьютер', 'laptop' => 'Ноутбук', 'server' => 'Сервер', 'all_in_one' => 'All-in-one',
                'workstation' => 'Ажлын станц', 'tablet' => 'Таблет', 'monitor' => 'Монитор', 'other' => 'Бусад'];
    $text = function ($v) {
        if ($v instanceof \UnitEnum) return method_exists($v, 'label') ? (string) $v->label() : (string) ($v instanceof \BackedEnum ? $v->value : $v->name);
        return is_scalar($v) ? (string) $v : '';
    };
    $statusMap = ['new' => 'Шинэ', 'active' => 'Идэвхтэй', 'in_use' => 'Идэвхтэй', 'in_repair' => 'Засварт', 'repair' => 'Засварт',
                  'storage' => 'Агуулахад', 'retired' => 'Актласан'];
    $in = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

{{-- Акт баталгаажуулалт --}}
@if ($unack > 0)
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex items-start gap-3">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-amber-100 text-lg">📝</div>
            <div>
                <div class="font-semibold text-amber-900">Акт баталгаажуулалт шаардлагатай</div>
                <p class="text-sm text-amber-800">Танд хариуцуулсан {{ $unack }} төхөөрөмжийн хүлээлцэх актыг шалгаж, хүлээн авснаа баталгаажуулна уу.</p>
            </div>
        </div>
        <form method="POST" action="{{ url('/my/acknowledge') }}">@csrf
            <button type="submit" class="rounded-xl bg-amber-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-600">Акт баталгаажуулах</button>
        </form>
    </div>
@endif
{{-- Бүх акт баталгаажсан үед байнгын мэдээ харуулахгүй: баталгаажуулсны дараа нэг удаа «Акт баталгаажлаа» гарна --}}

<div class="mb-6 flex flex-wrap items-center justify-end gap-3">
    <div class="flex flex-wrap gap-3">
        <button type="button" onclick="document.getElementById('dlg-issue').showModal()"
                class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">🛠 Эвдрэл мэдээлэх / Засварт өгөх</button>
        <button type="button" onclick="document.getElementById('dlg-request').showModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">＋ Шинэ төхөөрөмж захиалах</button>
    </div>
</div>

{{-- Summary --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-slate-600">Миний эзэмшиж буй төхөөрөмж</div>
        <div class="mt-3 text-3xl font-bold">{{ $total }} <span class="text-base font-medium text-slate-500">төхөөрөмж</span></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-slate-600">Идэвхтэй засварын хүсэлт</div>
        <div class="mt-3 text-3xl font-bold">{{ $openIssues }} <span class="text-base font-medium text-slate-500">хүсэлт</span></div>
    </div>
</div>

{{-- Assets --}}
<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between p-6 pb-3">
        <div><h2 class="font-bold">Эзэмшиж буй хөрөнгө</h2></div>
        @if ($q !== '')<a href="{{ url('/') }}" class="text-xs font-semibold text-blue-600">Хайлт цэвэрлэх</a>@endif
    </div>
    <ul class="divide-y divide-slate-100">
        @forelse ($assets as $c)
            @php
                $tv = $text($c->type);
                $sv = $text($c->status);
                // Ангилал (Дэлгэц, Mouse ...) байвал түүгээр, үгүй бол төрлөөр (Суурин компьютер ...)
                $tLabel = $c->category?->label() ?? $typeMap[$tv] ?? ($tv ?: 'Төхөөрөмж');
                $sLabel = $statusMap[$sv] ?? $sv;
                $sKey = strtolower($sv);
                $badge = str_contains($sKey, 'repair') ? 'bg-amber-50 text-amber-700'
                        : (str_contains($sKey, 'retire') || str_contains($sKey, 'stor') ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700');
            @endphp
            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $c->category?->icon() ?? 'M4 5h16v11H4zM8 20h8M12 16v4' }}"/></svg>
                    </div>
                    <div>
                        <div class="font-semibold">{{ $tLabel }}: {{ trim($c->brand . ' ' . $c->model) ?: $c->name }}</div>
                        <div class="text-xs text-slate-500">
                            Сериал: {{ $c->serial_number ?: '—' }} · Дугаар: {{ $c->inventory_number ?: '—' }}
                            @if ($c->acknowledged_at ?? null) · <span class="text-emerald-600">Акт баталгаажсан</span>@endif
                        </div>
                    </div>
                </div>
                <span class="flex flex-wrap items-center gap-2">
                    @if (isset($queueNumbers[$c->id]))
                        <span class="rounded-full bg-amber-100 px-3 py-1 font-mono text-xs font-bold text-amber-800" title="Засварын дараалал">#{{ $queueNumbers[$c->id] }}</span>
                        <span class="rounded-full px-3 py-1 text-xs {{ $c->repair_stage?->badgeClasses() }}">{{ $c->repair_stage?->label() }}</span>
                    @endif
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $badge }}">Төлөв: {{ $sLabel }}</span>
                </span>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-slate-400">
                {{ $q !== '' ? 'Хайлтад тохирох төхөөрөмж олдсонгүй.' : 'Танд одоогоор хариуцуулсан төхөөрөмж байхгүй байна.' }}
            </li>
        @endforelse
    </ul>
</section>

{{-- Миний хүсэлтүүд --}}
@php
    $myReqs = $hasReq ? AssetRequest::where('user_id', $me->id)->latest()->limit(8)->get() : collect();
    $rs = \App\Http\Controllers\AssetRequestController::STATUSES;
    $rb = ['pending' => 'bg-amber-50 text-amber-700', 'in_progress' => 'bg-blue-50 text-blue-700', 'done' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700'];
@endphp
@if ($myReqs->isNotEmpty())
<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="p-6 pb-3"><h2 class="font-bold">Миний хүсэлтүүд</h2><p class="text-xs text-slate-500">Сүүлд илгээсэн эвдрэл, захиалгууд</p></div>
    <ul class="divide-y divide-slate-100">
        @foreach ($myReqs as $r)
            <li class="flex flex-wrap items-start justify-between gap-3 px-6 py-4">
                <div>
                    <div class="font-semibold">{{ $r->type === 'issue' ? '🛠 Эвдрэл' : '＋ Захиалга' }}: {{ $r->subject ?: \Illuminate\Support\Str::limit($r->description, 50) }}</div>
                    <div class="text-xs text-slate-500">{{ $r->created_at->format('Y-m-d H:i') }}</div>
                    @if ($r->admin_note)<div class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">Хариу: {{ $r->admin_note }}</div>@endif
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $rb[$r->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $rs[$r->status] ?? $r->status }}</span>
            </li>
        @endforeach
    </ul>
</section>
@endif

{{-- Эвдрэл мэдээлэх: 1) аль төхөөрөмж, 2) ямар асуудал (товч), 3) тайлбар --}}
@php
    $issueOld = old('type') === 'issue';
    $pickedIssue = $issueOld ? (string) old('computer_id') : '';
    // Төхөөрөмжийн төрлөөр түгээмэл асуудлууд (дарахад «Асуудал» талбарт бичигдэнэ)
    $problems = [
        'computer' => ['Асахгүй байна', 'Удаан ажиллаж байна', 'Гацаж, унтарч байна', 'Интернэт холбогдохгүй', 'Програм ажиллахгүй', 'Чимээ гаргаж байна'],
        'monitor' => ['Дэлгэц харагдахгүй', 'Дэлгэц анивчиж байна', 'Өнгө, зураас гарч байна', 'Кабель, залгуур муу'],
        'input' => ['Ажиллахгүй байна', 'Зарим товч ажиллахгүй', 'Кабель, USB муу', 'Батарей дууссан'],
        'printer' => ['Хэвлэхгүй байна', 'Цаас гацаж байна', 'Бэх/хор дууссан', 'Бүдэг, толбо гарч байна', 'Сүлжээнд холбогдохгүй'],
        'ups' => ['Асахгүй байна', 'Дуут дохио өгч байна', 'Цахилгаан тасрахад унтарч байна'],
        'network' => ['Интернэт тасарч байна', 'Холбогдохгүй байна', 'Гэрэл асахгүй байна'],
        'other' => ['Ажиллахгүй байна', 'Гэмтсэн, хагарсан', 'Бусад асуудал'],
    ];
    $problemGroup = fn ($cat) => match ($cat?->value) {
        'processor', 'laptop', null => 'computer',
        'monitor' => 'monitor',
        'mouse', 'keyboard' => 'input',
        'printer', 'scanner' => 'printer',
        'ups' => 'ups',
        'network' => 'network',
        default => 'other',
    };
    $card = 'relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-left hover:bg-slate-50 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 has-[:checked]:ring-2 has-[:checked]:ring-rose-100';
@endphp
<dialog id="dlg-issue" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(42rem,94vw);max-height:92vh;border:0;">
    <form method="POST" action="{{ url('/my/requests') }}" class="flex max-h-[92vh] flex-col">@csrf
        <input type="hidden" name="type" value="issue">
        <div class="border-b border-slate-100 px-6 pb-4 pt-6">
            <h3 class="text-lg font-bold">🛠 Эвдрэл мэдээлэх / Засварт өгөх</h3>
            <p class="mt-1 text-sm text-slate-500">Мэдээлсний дараа IT Админ шалгаж, засварын дараалалд оруулна. Явцыг энэ хуудаснаас харна.</p>
        </div>

        <div class="space-y-6 overflow-y-auto px-6 py-5">
            {{-- 1. Төхөөрөмж --}}
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-slate-800"><span class="mr-1 inline-grid h-5 w-5 place-items-center rounded-full bg-rose-100 text-xs text-rose-700">1</span> Аль төхөөрөмж эвдэрсэн бэ?</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($assets as $c)
                        @php $inRepair = isset($queueNumbers[$c->id]); @endphp
                        <label class="{{ $card }} {{ $inRepair ? 'opacity-60' : '' }}">
                            <input type="radio" name="computer_id" value="{{ $c->id }}" class="sr-only" data-group="{{ $problemGroup($c->category) }}"
                                   @checked($pickedIssue === (string) $c->id) @disabled($inRepair)>
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ ($c->category ?? \App\Enums\EquipmentCategory::Processor)->icon() }}"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-medium text-slate-500">{{ $c->category?->label() ?? 'Компьютер' }}</span>
                                <span class="block truncate text-sm font-semibold text-slate-900" data-device-name>{{ trim($c->brand.' '.$c->model) ?: $c->name }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $c->inventory_number }}</span>
                                @if ($inRepair)<span class="mt-0.5 block text-[11px] font-semibold text-amber-700">Аль хэдийн засварт · #{{ $queueNumbers[$c->id] }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                    <label class="{{ $card }} border-dashed">
                        <input type="radio" name="computer_id" value="" class="sr-only" data-group="other" @checked($issueOld && $pickedIssue === '')>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-lg text-slate-500">?</span>
                        <span class="min-w-0 text-sm font-semibold text-slate-900">Өөр төхөөрөмж</span>
                    </label>
                </div>
                @if ($issueOld) @error('computer_id')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror @endif
            </fieldset>

            {{-- 2. Асуудал --}}
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-slate-800"><span class="mr-1 inline-grid h-5 w-5 place-items-center rounded-full bg-rose-100 text-xs text-rose-700">2</span> Ямар асуудал гарсан бэ?</legend>
                <div id="issue-chips" class="mb-2 flex flex-wrap gap-2">
                    @foreach ($problems as $group => $list)
                        @foreach ($list as $p)
                            <button type="button" data-chip="{{ $p }}" data-chip-group="{{ $group }}"
                                    class="hidden !rounded-full !border-slate-300 !bg-white !px-3 !py-1.5 !text-xs font-medium !text-slate-700 hover:!border-rose-300 hover:!bg-rose-50">{{ $p }}</button>
                        @endforeach
                    @endforeach
                </div>
                <input name="subject" id="issue-subject" type="text" maxlength="150" value="{{ $issueOld ? old('subject') : '' }}" class="{{ $in }}" placeholder="Товчоос сонгох эсвэл бичих: Асахгүй байна">
            </fieldset>

            {{-- 3. Тайлбар --}}
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-slate-800"><span class="mr-1 inline-grid h-5 w-5 place-items-center rounded-full bg-rose-100 text-xs text-rose-700">3</span> Дэлгэрэнгүй тайлбар <span class="text-rose-500">*</span></legend>
                <textarea name="description" id="issue-description" rows="3" required maxlength="1000" class="{{ $in }} !mt-0" placeholder="Юу болсон, хэзээнээс эхэлсэн, ямар алдааны мэдээлэл гарч байгааг бичнэ үү">{{ $issueOld ? old('description') : '' }}</textarea>
                @if ($issueOld) @error('description')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror @endif
            </fieldset>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <p id="issue-summary" class="min-w-0 flex-1 truncate text-sm text-slate-600">Төхөөрөмжөө сонгоно уу.</p>
            <div class="flex gap-3">
                <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Цуцлах</button>
                <button type="submit" class="rounded-xl !border-rose-600 !bg-rose-600 px-5 py-2.5 text-sm font-semibold !text-white hover:!bg-rose-700">Мэдээлэх</button>
            </div>
        </div>
    </form>
</dialog>
<script>
// Эвдрэл мэдээлэх: төхөөрөмжөөс хамааран асуудлын товч, доод мөрөнд товч дүгнэлт
(function () {
    const dlg = document.getElementById('dlg-issue');
    const radios = Array.from(dlg.querySelectorAll('input[name="computer_id"]'));
    const chips = Array.from(dlg.querySelectorAll('[data-chip]'));
    const subject = document.getElementById('issue-subject');
    const summary = document.getElementById('issue-summary');

    function update() {
        const picked = radios.find(function (r) { return r.checked; });
        const group = picked ? picked.dataset.group : 'computer';
        chips.forEach(function (c) {
            c.classList.toggle('hidden', c.dataset.chipGroup !== group);
            // Сонгосон товч: цайвар ягаан дэвсгэр, улаан хүрээ, бичиг нь хар (уншигдахуйц)
            const on = subject.value.trim() === c.dataset.chip;
            c.classList.toggle('!bg-white', !on);
            c.classList.toggle('!border-slate-300', !on);
            c.classList.toggle('!text-slate-700', !on);
            c.classList.toggle('!bg-rose-100', on);
            c.classList.toggle('!border-rose-500', on);
            c.classList.toggle('!text-slate-900', on);
            c.classList.toggle('font-semibold', on);
        });
        const name = picked ? (picked.value ? picked.closest('label').querySelector('[data-device-name]').textContent : 'Өөр төхөөрөмж') : '';
        summary.textContent = name ? '🛠 ' + name + (subject.value.trim() ? ' — ' + subject.value.trim() : '') : 'Төхөөрөмжөө сонгоно уу.';
    }

    radios.forEach(function (r) { r.addEventListener('change', update); });
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            subject.value = c.dataset.chip;
            update();
            document.getElementById('issue-description').focus();
        });
    });
    subject.addEventListener('input', update);
    // Ганц төхөөрөмжтэй бол шууд сонгогдсон байна
    const enabled = radios.filter(function (r) { return r.value && !r.disabled; });
    if (!radios.some(function (r) { return r.checked; }) && enabled.length === 1) enabled[0].checked = true;
    update();
})();
</script>

{{-- Шинэ төхөөрөмж захиалах: төхөөрөмж, тоо, шалтгааныг сонгоод илгээнэ (жагсаалтад байхгүй бол гараар) --}}
@php
    $owned = \App\Models\Computer::query()->where('assigned_user_id', $me->id)->get()
        ->countBy(fn ($c) => $c->category?->value ?? 'other');
    $reasons = [
        'Шинэ ажилтан, ажлын байрны тоноглол',
        'Одоогийн төхөөрөмж эвдэрсэн / ажиллахгүй',
        'Одоогийн төхөөрөмж хуучирсан, удаан',
        'Ажлын шаардлагаар нэмэлт төхөөрөмж хэрэгтэй',
    ];
@endphp
<dialog id="dlg-request" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(36rem,94vw);border:0;">
    <form method="POST" action="{{ url('/my/requests') }}" class="p-6" id="request-form">@csrf
        <input type="hidden" name="type" value="request">
        <input type="hidden" name="subject" id="req-subject" value="{{ old('type') === 'request' ? old('subject') : '' }}">
        <h3 class="text-lg font-bold">Шинэ төхөөрөмж захиалах</h3>

        {{-- 1. Төхөөрөмж --}}
        <div class="mt-4 text-sm font-medium text-slate-700">Ямар төхөөрөмж хэрэгтэй вэ? <span class="text-rose-500">*</span></div>
        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5" id="req-items">
            @foreach (\App\Enums\EquipmentCategory::cases() as $cat)
                <button type="button" data-item="{{ $cat->label() }}" data-other="{{ $cat === \App\Enums\EquipmentCategory::Other ? 1 : 0 }}"
                        class="req-item relative flex flex-col items-center gap-1 rounded-xl !border !border-slate-200 !bg-white !px-2 !py-3 !text-xs !font-medium !text-slate-700 hover:!border-blue-300">
                    <svg class="h-6 w-6 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $cat->icon() }}"/></svg>
                    <span class="text-center leading-tight">{{ $cat->label() }}</span>
                    @if ($owned[$cat->value] ?? 0)
                        <span class="text-[10px] font-normal text-slate-400">Танд {{ $owned[$cat->value] }} бий</span>
                    @endif
                </button>
            @endforeach
        </div>
        <input type="text" id="req-other" maxlength="120" placeholder="Ямар төхөөрөмж вэ? (жишээ: Веб камер, чихэвч)" class="{{ $in }} hidden">

        {{-- 2. Тоо --}}
        <div class="mt-4 flex items-center gap-3">
            <span class="text-sm font-medium text-slate-700">Тоо ширхэг</span>
            <div class="flex overflow-hidden rounded-lg border border-slate-300" id="req-qty">
                @foreach ([1, 2, 3, 4, 5] as $n)
                    <button type="button" data-qty="{{ $n }}" class="req-qty !rounded-none !border-0 !border-r !border-slate-300 !bg-white !px-3 !py-1.5 !text-sm !font-medium !text-slate-700 last:!border-r-0">{{ $n }}</button>
                @endforeach
            </div>
        </div>

        {{-- 3. Шалтгаан: сонгоход бөглөгдөнө, засаж болно --}}
        <div class="mt-4 text-sm font-medium text-slate-700">Шалтгаан, тайлбар <span class="text-rose-500">*</span></div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach ($reasons as $r)
                <button type="button" data-reason="{{ $r }}" class="req-reason !rounded-full !border !border-slate-200 !bg-slate-50 !px-3 !py-1 !text-xs !font-normal !text-slate-700 hover:!bg-blue-50">{{ $r }}</button>
            @endforeach
        </div>
        <textarea name="description" id="req-desc" rows="3" required maxlength="1000" class="{{ $in }}" placeholder="Сонгох эсвэл бичих: яагаад хэрэгтэй, ямар шаардлагатай вэ">{{ old('type') === 'request' ? old('description') : '' }}</textarea>
        @if (old('type') === 'request') @error('description')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror @endif

        <p id="req-preview" class="mt-3 hidden rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-900"></p>
        <p id="req-error" class="mt-2 hidden text-xs text-rose-600">Ямар төхөөрөмж хэрэгтэйг сонгоно уу.</p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Цуцлах</button>
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Захиалах</button>
        </div>
    </form>
</dialog>


<script>
// Шинэ төхөөрөмж захиалах: сонголтоос «Дэлгэц ×2» гэх мэт гарчиг бүрдүүлнэ
(function () {
    const form = document.getElementById('request-form');
    if (!form) return;
    const subject = document.getElementById('req-subject');
    const other = document.getElementById('req-other');
    const desc = document.getElementById('req-desc');
    const preview = document.getElementById('req-preview');
    const error = document.getElementById('req-error');
    const items = Array.from(form.querySelectorAll('.req-item'));
    const qtys = Array.from(form.querySelectorAll('.req-qty'));
    let item = null, isOther = false, qty = 1;

    const on = ['!border-blue-500', '!bg-blue-50', 'ring-2', 'ring-blue-100'];
    function mark(list, active) { list.forEach(function (b) { on.forEach(function (c) { b.classList.toggle(c, b === active); }); }); }

    function update() {
        const name = isOther ? other.value.trim() : item;
        subject.value = name ? (name + (qty > 1 ? ' ×' + qty : '')) : '';
        preview.classList.toggle('hidden', !subject.value);
        preview.textContent = subject.value ? 'Захиалга: ' + subject.value : '';
        if (subject.value) error.classList.add('hidden');
    }

    items.forEach(function (b) {
        b.addEventListener('click', function () {
            item = b.dataset.item; isOther = b.dataset.other === '1';
            mark(items, b);
            other.classList.toggle('hidden', !isOther);
            if (isOther) other.focus();
            update();
        });
    });
    qtys.forEach(function (b) {
        b.addEventListener('click', function () {
            qty = parseInt(b.dataset.qty, 10);
            // Сонгосон тоо: бараан дэвсгэр, цагаан бичиг (!bg-white-ийг хасахгүй бол дэвсгэр цагаан хэвээр үлдэж тоо харагдахгүй)
            qtys.forEach(function (x) {
                const sel = x === b;
                x.classList.toggle('!bg-white', !sel);
                x.classList.toggle('!text-slate-700', !sel);
                x.classList.toggle('!bg-slate-800', sel);
                x.classList.toggle('!text-white', sel);
            });
            update();
        });
    });
    other.addEventListener('input', update);
    form.querySelectorAll('.req-reason').forEach(function (b) {
        b.addEventListener('click', function () { desc.value = b.dataset.reason; desc.focus(); });
    });
    form.addEventListener('submit', function (e) {
        if (!subject.value) { e.preventDefault(); error.classList.remove('hidden'); }
    });

    qtys[0].click(); // анхдагч: 1
})();
</script>

@if ($errors->any() && in_array(old('type'), ['issue', 'request'], true))
    <script>
        document.getElementById('{{ old('type') === 'issue' ? 'dlg-issue' : 'dlg-request' }}').showModal();
    </script>
@endif