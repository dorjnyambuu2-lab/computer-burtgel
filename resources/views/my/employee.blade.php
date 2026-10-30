@php
    use App\Models\AssetRequest;
    use Illuminate\Support\Facades\Schema;

    $me = auth()->user();
    $q = trim((string) request('q'));

    // Миний төхөөрөмж: хэрэглэгчийн ID-аар (нэр солигдсон ч алга болохгүй). Акт баталгаажуулах, эвдрэл
    // мэдээлэх (AssetRequestController::mine) мөн ижил нөхцөлөөр шалгадаг тул энд ч ижил байна.
    $mineQuery = \App\Models\Computer::query()->where('assigned_user_id', $me->id);
    $allMine = (clone $mineQuery)->orderBy('name')->get();
    $assets = $q === '' ? $allMine : $allMine->filter(fn ($c) => collect([$c->name, $c->serial_number, $c->inventory_number, $c->brand, $c->model])
        ->contains(fn ($v) => $v !== null && mb_stripos($v, $q) !== false))->values();
    $total = $allMine->count();
    $queueNumbers = \App\Models\Computer::repairQueueNumbers();

    $hasReq = Schema::hasTable('asset_requests');
    // Шийдэгдээгүй (хүлээгдэж буй, хийгдэж буй) бүх хүсэлт: эвдрэл + захиалга
    $openRequests = $hasReq ? AssetRequest::where('user_id', $me->id)->whereIn('status', ['pending', 'in_progress'])->count() : 0;
    $unack = $allMine->whereNull('acknowledged_at')->count();

    $in = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

@php
    // Иж бүрдлээр бүлэглэнэ: үндсэн төхөөрөмж (компьютер ...) → түүний дагалдах хэрэгслүүд (дэлгэц, mouse ...)
    $assetIds = $assets->pluck('id');
    $children = $assets->filter(fn ($c) => $c->kit_parent_id && $assetIds->contains($c->kit_parent_id))->groupBy('kit_parent_id');
    $mains = $assets->reject(fn ($c) => $c->kit_parent_id && $assetIds->contains($c->kit_parent_id))
        ->sortBy(fn ($c) => [in_array($c->category?->value, ['processor', 'laptop'], true) ? 0 : 1, $c->name])->values();
    $inRepair = $allMine->filter(fn ($c) => isset($queueNumbers[$c->id]))->count();
    $deviceName = fn ($c) => trim($c->brand.' '.$c->model) ?: $c->name;
    $iconPath = fn ($c) => ($c->category ?? \App\Enums\EquipmentCategory::Processor)->icon();
@endphp

{{-- Үндсэн үйлдлүүд --}}
<div class="mb-5 flex flex-wrap items-center justify-end gap-4">
    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="document.getElementById('dlg-issue').showModal()"
                class="inline-flex h-10 !min-h-0 items-center gap-2 !rounded-lg !border !border-rose-200 !bg-white !px-4 !py-0 !text-sm !font-semibold !text-rose-600 shadow-sm transition hover:-translate-y-0.5 hover:!bg-rose-50 hover:shadow-md">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
            Эвдрэл мэдээлэх
        </button>
        <button type="button" onclick="document.getElementById('dlg-request').showModal()"
                class="inline-flex h-10 !min-h-0 items-center gap-2 !rounded-lg !border-blue-600 !bg-blue-600 !px-4 !py-0 !text-sm !font-semibold !text-white shadow-sm transition hover:-translate-y-0.5 hover:!bg-blue-700 hover:shadow-md">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Төхөөрөмж захиалах
        </button>
    </div>
</div>

{{-- Акт баталгаажуулалт (хүлээгдэж буй бол) --}}
@if ($unack > 0)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12l2 2 4-4M7 3h10l2 3v15H5V6z"/></svg>
            </span>
            <div>
                <div class="font-semibold text-amber-900">{{ $unack }} төхөөрөмжийн хүлээлцэх акт баталгаажуулаагүй</div>
                <p class="text-sm text-amber-800">Төхөөрөмжүүдээ шалгаад хүлээн авснаа баталгаажуулна уу.</p>
            </div>
        </div>
        <form method="POST" action="{{ url('/my/acknowledge') }}">@csrf
            <button type="submit" class="h-10 !min-h-0 !rounded-lg !border-amber-500 !bg-amber-500 !px-5 !py-0 !text-sm !font-semibold !text-white hover:!bg-amber-600">Баталгаажуулах</button>
        </form>
    </div>
@endif

{{-- Тоо: дарахад доорх жагсаалт шүүгдэнэ / хүсэлтүүд рүү гүйлгэнэ --}}
<div class="mb-5 grid gap-3 sm:grid-cols-3">
    @foreach ([
        ['all', 'Миний төхөөрөмж', $total, 'bg-blue-50 text-blue-600', 'M4 5h16v11H4zM8 20h8M12 16v4'],
        ['repair', 'Засварт байгаа', $inRepair, 'bg-amber-50 text-amber-600', 'M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z'],
        ['requests', 'Хүсэлт', $openRequests, 'bg-violet-50 text-violet-600', 'M9 5h10M9 12h10M9 19h10M4 5h.01M4 12h.01M4 19h.01'],
    ] as [$key, $label, $value, $tone, $path])
        <button type="button" data-emp-card="{{ $key }}"
                class="flex !min-h-0 items-center gap-4 !rounded-2xl !border !border-slate-200 !bg-white !px-5 !py-4 text-left !font-normal !text-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:!border-slate-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 data-[active]:!border-blue-400 data-[active]:ring-2 data-[active]:ring-blue-100">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tone }}">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
            </span>
            <span>
                <span class="block text-2xl font-bold leading-none text-slate-900">{{ $value }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ $label }}</span>
            </span>
        </button>
    @endforeach
</div>

{{-- Миний төхөөрөмж: иж бүрдлээр, мөр дээр дарахад дэлгэрэнгүй --}}
<section id="my-devices" class="scroll-mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
        <h2 class="!mb-0 text-xs font-semibold uppercase tracking-wide text-slate-500">Миний төхөөрөмж<span data-emp-filter-label hidden> · Засварт байгаа</span></h2>
        <span class="flex items-center gap-3">
            <button type="button" data-emp-card="all" data-emp-reset hidden class="!min-h-0 !border-0 !bg-transparent !p-0 text-xs !font-semibold !text-blue-600 hover:underline">Бүгдийг харах</button>
            @if ($q !== '')<a href="{{ url('/') }}" class="text-xs font-semibold !text-blue-600 hover:underline">Хайлт цэвэрлэх</a>@endif
        </span>
    </div>
    <ul class="divide-y divide-slate-100">
        <li data-emp-repair-empty hidden class="px-5 py-12 text-center text-sm text-slate-400">Засварт байгаа төхөөрөмж алга.</li>
        @forelse ($mains as $c)
            @php $kitInRepair = isset($queueNumbers[$c->id]) || ($children[$c->id] ?? collect())->contains(fn ($i) => isset($queueNumbers[$i->id])); @endphp
            <li data-emp-device @if ($kitInRepair) data-repair @endif>
                <a href="{{ route('my.computers.show', $c->id) }}" class="flex items-center gap-4 px-5 py-4 !text-slate-900 transition hover:bg-slate-50">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconPath($c) }}"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-semibold">{{ $deviceName($c) }}</span>
                            <span class="text-xs text-slate-500">{{ $c->category?->label() ?? $c->type?->label() }}</span>
                        </span>
                        <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
                            <span class="font-mono">{{ $c->inventory_number }}</span>
                            @if ($c->serial_number)<span>S/N {{ $c->serial_number }}</span>@endif
                            @if ($c->acknowledged_at)<span class="text-emerald-600">✓ Акт баталгаажсан</span>@else<span class="text-amber-600">Акт хүлээгдэж байна</span>@endif
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                        @if (isset($queueNumbers[$c->id]))
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800" title="Засварын дараалал">Засварт #{{ $queueNumbers[$c->id] }} · {{ $c->repair_stage?->label() ?? 'Дараалалд' }}</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $c->status->badgeClasses() }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $c->status->label() }}</span>
                        @endif
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </span>
                </a>
                {{-- Иж бүрдлийн дагалдах хэрэгслүүд --}}
                @if ($children->has($c->id))
                    <div class="flex flex-wrap gap-2 px-5 pb-4 pl-20">
                        @foreach ($children[$c->id]->sortBy('kit_index') as $item)
                            <a href="{{ route('my.computers.show', $item->id) }}" title="{{ $item->inventory_number }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs !text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400" aria-hidden="true"><path d="{{ $iconPath($item) }}"/></svg>
                                {{ $item->category?->label() }}@if (trim($item->brand.' '.$item->model) !== '') · {{ trim($item->brand.' '.$item->model) }}@endif
                                @if (isset($queueNumbers[$item->id]))<span class="font-semibold text-amber-700">· Засварт</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </li>
        @empty
            <li data-emp-device class="px-5 py-12 text-center text-sm text-slate-400">
                {{ $q !== '' ? 'Хайлтад тохирох төхөөрөмж олдсонгүй.' : 'Танд одоогоор хариуцуулсан төхөөрөмж байхгүй байна.' }}
            </li>
        @endforelse
    </ul>
</section>

{{-- Миний хүсэлтүүд --}}
@php
    $myReqs = $hasReq ? AssetRequest::where('user_id', $me->id)->with(['computer:id,name,brand,model', 'items'])->latest()->limit(8)->get() : collect();
    $rb = ['pending' => 'bg-amber-100 text-amber-800', 'in_progress' => 'bg-blue-100 text-blue-700', 'done' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'];
@endphp
@if ($hasReq)
<section id="my-requests" class="mt-5 scroll-mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition">
    <h2 class="!mb-0 border-b border-slate-100 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Миний хүсэлтүүд</h2>
    <ul class="divide-y divide-slate-100">
        @forelse ($myReqs as $r)
            <li class="flex flex-wrap items-start gap-3 px-5 py-3.5">
                <span class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $r->type === 'issue' ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $r->type === 'issue' ? 'Эвдрэл' : 'Захиалга' }}</span>
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-slate-900">{{ $r->subject ?: \Illuminate\Support\Str::limit($r->description, 60) }}@if ($r->computer)<span class="font-normal text-slate-500"> · {{ trim($r->computer->brand.' '.$r->computer->model) ?: $r->computer->name }}</span>@endif</div>
                    <div class="text-xs text-slate-400">{{ $r->created_at->format('Y-m-d H:i') }}</div>
                    {{-- Захиалгын мөр бүрийн төлөв (олон төхөөрөмжтэй бол) --}}
                    @if ($r->items->count() > 1 || $r->items->contains(fn ($i) => $i->note))
                        <ul class="mt-2 space-y-1">
                            @foreach ($r->items as $item)
                                <li class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="text-slate-700">{{ $item->label() }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ \App\Models\AssetRequestItem::BADGES[$item->status] ?? '' }}">{{ $item->statusLabel() }}</span>
                                    @if ($item->note)<span class="text-xs text-slate-500">— {{ $item->note }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($r->admin_note)<div class="mt-1.5 rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600"><span class="font-semibold text-slate-700">Хариу:</span> {{ $r->admin_note }}</div>@endif
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $rb[$r->status] ?? 'bg-slate-100 text-slate-600' }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $r->statusLabel() }}</span>
            </li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-400">Илгээсэн хүсэлт алга.</li>
        @endforelse
    </ul>
</section>
@endif

<script>
(() => {
    const cards = document.querySelectorAll('[data-emp-card]');
    const rows = document.querySelectorAll('[data-emp-device]');
    const repairEmpty = document.querySelector('[data-emp-repair-empty]');
    const label = document.querySelector('[data-emp-filter-label]');
    const reset = document.querySelector('[data-emp-reset]');
    const go = (id) => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const filter = (mode) => {
        const repairOnly = mode === 'repair';
        let shown = 0;
        rows.forEach((li) => {
            const show = !repairOnly || li.hasAttribute('data-repair');
            li.hidden = !show;
            if (show) shown++;
        });
        if (repairEmpty) repairEmpty.hidden = !(repairOnly && shown === 0);
        if (label) label.hidden = !repairOnly;
        if (reset) reset.hidden = !repairOnly;
        cards.forEach((c) => c.toggleAttribute('data-active', repairOnly && c.dataset.empCard === 'repair'));
    };

    cards.forEach((card) => card.addEventListener('click', () => {
        const mode = card.dataset.empCard;
        if (mode === 'requests') {
            const box = document.getElementById('my-requests');
            go('my-requests');
            box?.classList.add('ring-2', 'ring-violet-200');
            setTimeout(() => box?.classList.remove('ring-2', 'ring-violet-200'), 1200);
            return;
        }
        filter(mode);
        if (!card.hasAttribute('data-emp-reset')) go('my-devices');
    }));
})();
</script>

{{-- Эвдрэл мэдээлэх: 1) аль төхөөрөмж, 2) ямар асуудал (товч), 3) тайлбар --}}
@php
    $issueOld = old('type') === 'issue';
    $pickedIssue = $issueOld ? (string) old('computer_id') : '';
    // Шийдэгдээгүй эвдрлийн хүсэлттэй төхөөрөмжийг дахин мэдээлэхгүй (давхар хүсэлт)
    $reportedIds = $hasReq ? AssetRequest::where('user_id', $me->id)->where('type', 'issue')->whereIn('status', ['pending', 'in_progress'])
        ->whereNotNull('computer_id')->pluck('computer_id')->map(fn ($id) => (int) $id)->all() : [];
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
                    @foreach ($allMine as $c)
                        @php
                            $busy = isset($queueNumbers[$c->id]);
                            $reported = in_array($c->id, $reportedIds, true);
                        @endphp
                        <label class="{{ $card }} {{ $busy || $reported ? 'cursor-not-allowed opacity-60' : '' }}">
                            <input type="radio" name="computer_id" value="{{ $c->id }}" class="sr-only" data-group="{{ $problemGroup($c->category) }}"
                                   @checked($pickedIssue === (string) $c->id) @disabled($busy || $reported)>
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ ($c->category ?? \App\Enums\EquipmentCategory::Processor)->icon() }}"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-medium text-slate-500">{{ $c->category?->label() ?? 'Компьютер' }}</span>
                                <span class="block truncate text-sm font-semibold text-slate-900" data-device-name>{{ trim($c->brand.' '.$c->model) ?: $c->name }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $c->inventory_number }}</span>
                                @if ($busy)<span class="mt-0.5 block text-[11px] font-semibold text-amber-700">Аль хэдийн засварт · #{{ $queueNumbers[$c->id] }}</span>
                                @elseif ($reported)<span class="mt-0.5 block text-[11px] font-semibold text-amber-700">Эвдрэл мэдээлсэн, хариу хүлээгдэж байна</span>@endif
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
    $owned = $allMine->countBy(fn ($c) => $c->category?->value ?? 'other');
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
        <h3 class="text-lg font-bold">Шинэ төхөөрөмж захиалах</h3>

        {{-- 1. Төхөөрөмж: олныг сонгож болно, тус бүрт тоо --}}
        <div class="mt-4 text-sm font-medium text-slate-700">Ямар төхөөрөмж хэрэгтэй вэ? <span class="text-rose-500">*</span> <span class="font-normal text-slate-400">· олныг сонгож болно</span></div>
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
        {{-- 2. Сонгосон төхөөрөмж бүр: тоо (− / +), хасах; «Дагалдах хэрэгсэл»-д нэрийг бичнэ --}}
        <ul id="req-picked" class="mt-3 hidden divide-y divide-slate-100 rounded-xl border border-slate-200"></ul>
        <div id="req-hidden"></div>
        @if (old('type') === 'request') @error('items.*.name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror @endif

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
// Шинэ төхөөрөмж захиалах: олон төхөөрөмж сонгож, тус бүрт тоо өгнө → items[][name, quantity]
(function () {
    const form = document.getElementById('request-form');
    if (!form) return;
    const desc = document.getElementById('req-desc');
    const preview = document.getElementById('req-preview');
    const error = document.getElementById('req-error');
    const list = document.getElementById('req-picked');
    const hidden = document.getElementById('req-hidden');
    const buttons = Array.from(form.querySelectorAll('.req-item'));
    // [{ button, name, other, qty }] — «Дагалдах хэрэгсэл»-д name-ийг ажилтан бичнэ
    let picked = [];

    const on = ['!border-blue-500', '!bg-blue-50', 'ring-2', 'ring-blue-100'];
    const nameOf = function (p) { return (p.other ? p.input.value : p.name).trim(); };
    const label = function (p) { return nameOf(p) + (p.qty > 1 ? ' ×' + p.qty : ''); };

    function render() {
        buttons.forEach(function (b) {
            const sel = picked.some(function (p) { return p.button === b; });
            on.forEach(function (c) { b.classList.toggle(c, sel); });
        });
        list.innerHTML = '';
        list.classList.toggle('hidden', picked.length === 0);
        picked.forEach(function (p) {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-2 px-3 py-2';
            const name = document.createElement('span');
            name.className = 'min-w-0 flex-1 text-sm font-medium text-slate-800';
            if (p.other) {
                name.appendChild(p.input); // бичсэн нэр алга болохгүй (дахин зурахад ижил элемент)
            } else {
                name.textContent = p.name;
            }
            const step = document.createElement('span');
            step.className = 'flex shrink-0 items-center overflow-hidden rounded-lg border border-slate-300';
            step.innerHTML = '<button type="button" data-dec aria-label="Хасах" class="!rounded-none !border-0 !bg-white !px-2.5 !py-1 !text-sm !text-slate-600 hover:!bg-slate-100">−</button>'
                + '<span class="min-w-8 px-1 text-center text-sm font-semibold text-slate-800">' + p.qty + '</span>'
                + '<button type="button" data-inc aria-label="Нэмэх" class="!rounded-none !border-0 !bg-white !px-2.5 !py-1 !text-sm !text-slate-600 hover:!bg-slate-100">+</button>';
            step.querySelector('[data-dec]').addEventListener('click', function () { p.qty = Math.max(1, p.qty - 1); render(); });
            step.querySelector('[data-inc]').addEventListener('click', function () { p.qty = Math.min(20, p.qty + 1); render(); });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Хасах');
            remove.className = '!rounded-lg !border-0 !bg-transparent !px-2 !py-1 !text-slate-400 hover:!bg-rose-50 hover:!text-rose-600';
            remove.textContent = '✕';
            remove.addEventListener('click', function () { picked = picked.filter(function (x) { return x !== p; }); render(); });
            li.append(name, step, remove);
            list.appendChild(li);
        });
        update();
    }

    function update() {
        const valid = picked.filter(function (p) { return nameOf(p) !== ''; });
        preview.classList.toggle('hidden', valid.length === 0);
        preview.textContent = valid.length ? 'Захиалга: ' + valid.map(label).join(', ') : '';
        if (valid.length) error.classList.add('hidden');
    }

    function add(button, qty, otherName) {
        const p = { button: button, name: button.dataset.item, other: button.dataset.other === '1', qty: qty || 1 };
        if (p.other) {
            p.input = document.createElement('input');
            p.input.type = 'text';
            p.input.maxLength = 120;
            p.input.placeholder = 'Ямар төхөөрөмж вэ? (жишээ: Веб камер)';
            p.input.className = 'w-full !mt-0 rounded-lg border border-slate-300 px-2 py-1 text-sm';
            p.input.value = otherName || '';
            p.input.addEventListener('input', update);
        }
        picked.push(p);
        render();
        if (p.other && !otherName) p.input.focus();
    }

    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            const existing = picked.find(function (p) { return p.button === b; });
            if (existing) { picked = picked.filter(function (p) { return p !== existing; }); render(); }
            else add(b);
        });
    });
    form.querySelectorAll('.req-reason').forEach(function (b) {
        b.addEventListener('click', function () { desc.value = b.dataset.reason; desc.focus(); });
    });
    form.addEventListener('submit', function (e) {
        const valid = picked.filter(function (p) { return nameOf(p) !== ''; });
        if (!valid.length) { e.preventDefault(); error.classList.remove('hidden'); return; }
        hidden.innerHTML = '';
        valid.forEach(function (p, i) {
            [['name', nameOf(p)], ['quantity', p.qty]].forEach(function (pair) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[' + i + '][' + pair[0] + ']';
                input.value = pair[1];
                hidden.appendChild(input);
            });
        });
    });

    // Алдаатай буцаж нээгдвэл өмнөх сонголтыг сэргээнэ
    const otherButton = buttons.find(function (b) { return b.dataset.other === '1'; });
    (@json(old('type') === 'request' ? array_values((array) old('items', [])) : [])).forEach(function (it) {
        const b = buttons.find(function (x) { return x.dataset.item === it.name && x.dataset.other !== '1'; });
        if (b) add(b, parseInt(it.quantity, 10) || 1);
        else if (otherButton) add(otherButton, parseInt(it.quantity, 10) || 1, it.name);
    });
})();
</script>

@if ($errors->any() && in_array(old('type'), ['issue', 'request'], true))
    <script>
        document.getElementById('{{ old('type') === 'issue' ? 'dlg-issue' : 'dlg-request' }}').showModal();
    </script>
@endif