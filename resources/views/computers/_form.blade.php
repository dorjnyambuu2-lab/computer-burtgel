@php
    $computer = $computer ?? new \App\Models\Computer();
    $isEdit = $computer->exists;
    $detectedFields = $detected ?? []; // скриптээр автоматаар бөглөгдсөн талбарууд

    // Утгыг (enum, огноо, string) input-д тохирох текст болгох
    $val = function (string $f) use ($computer) {
        $v = old($f, $computer->{$f});
        if ($v instanceof \BackedEnum) return $v->value;
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        // Үнэ: «2500000.00» → «2500000»
        if ($f === 'purchase_price' && is_numeric($v) && fmod((float) $v, 1.0) == 0.0) return (string) (int) $v;
        return $v;
    };

    $label = function ($case, array $map = []) {
        if (method_exists($case, 'label')) return $case->label();
        return $map[$case->value] ?? $case->value;
    };

    $statusMap = ['active' => 'Ашиглагдаж байгаа', 'in_use' => 'Ашиглагдаж байгаа', 'repair' => 'Засварт', 'maintenance' => 'Засварт',
                  'storage' => 'Агуулахад', 'stock' => 'Агуулахад', 'retired' => 'Актласан', 'disposed' => 'Актласан'];
    $statuses = class_exists(\App\Enums\ComputerStatus::class)
        ? collect(\App\Enums\ComputerStatus::cases())->mapWithKeys(fn($c) => [$c->value => $label($c, $statusMap)])->all()
        : ['active' => 'Ашиглагдаж байгаа', 'repair' => 'Засварт', 'storage' => 'Агуулахад', 'retired' => 'Актласан'];

    $typeMap = ['desktop' => 'Суурин компьютер', 'laptop' => 'Зөөврийн компьютер', 'server' => 'Сервер',
                'all_in_one' => 'Бүгд нэг дор (All-in-one)', 'workstation' => 'Ажлын станц', 'tablet' => 'Таблет', 'other' => 'Бусад'];
    $types = class_exists(\App\Enums\ComputerType::class)
        ? collect(\App\Enums\ComputerType::cases())->mapWithKeys(fn($c) => [$c->value => $label($c, $typeMap)])->all()
        : $typeMap;

    // Ангилал: процессор, дэлгэц, mouse, keyboard, printer ... ([утга => шошго])
    $categories = \App\Enums\EquipmentCategory::options();

    // Техникийн үзүүлэлтийн санал болгох утгууд: жагсаалтаас сонгоно, байхгүй бол гараар бичнэ (App\Support\SpecSuggestions)
    $modelsByBrand = \App\Support\SpecSuggestions::modelsByBrand();
    $suggest = \App\Support\SpecSuggestions::suggest();
    // Олон ангилал сонгоход төхөөрөмж бүрийн картанд гарах талбарууд (ангиллаас хамаарна)
    $itemConfig = \App\Support\SpecSuggestions::itemConfig();

    $in  = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $lb  = 'block text-sm font-medium text-slate-700';
@endphp



@php
    $sections = [
        'Үндсэн мэдээлэл' => [
            // Шинээр бүртгэхэд хоосон орхивол автоматаар үүснэ (засахад заавал)
            ['inventory_number', 'Бүртгэлийн дугаар', 'text', $isEdit ? ['required' => true] : ['placeholder' => 'Автоматаар үүснэ', 'readonly' => true]],
            ['name', 'Компьютерийн нэр', 'text', $isEdit ? ['required' => true] : ['placeholder' => 'Хоосон бол: Үйлдвэрлэгч + Загвар']],
            ['type', 'Төрөл', 'type', []],
            ['category', 'Ангилал', 'category', []],
            ...($isEdit ? [] : [['kit_parent_id', 'Иж бүрдэл (дагалдах хэрэгсэл бол)', 'kit', []]]),
            ['status', 'Төлөв', 'status', []],
        ],
        'Техникийн үзүүлэлт' => [
            ['brand', 'Үйлдвэрлэгч', 'text', ['placeholder' => 'Сонгох эсвэл бичих: Dell, HP, Lenovo...']],
            ['model', 'Загвар', 'text', ['placeholder' => 'Сонгох эсвэл бичих']],
            ['serial_number', 'Серийн дугаар', 'text', []],
            ['cpu', 'Процессор (CPU)', 'text', ['placeholder' => 'Intel Core i5-12400']],
            ['ram_gb', 'RAM (GB)', 'number', ['placeholder' => '8']],
            ['storage', 'Санах ой (диск)', 'text', ['placeholder' => 'SSD 512GB']],
            ['os', 'Үйлдлийн систем', 'text', ['placeholder' => 'Windows 11 Pro']],
            ['specs', 'Үзүүлэлт', 'text', ['placeholder' => 'Дэлгэцийн хэмжээ, printer-ийн төрөл гэх мэт']],
        ],
        'Сүлжээ' => [
            ['ip_address', 'IP хаяг', 'text', ['placeholder' => '192.168.1.10']],
            ['mac_address', 'MAC хаяг', 'text', ['placeholder' => 'AA:BB:CC:DD:EE:FF']],
        ],
        'Байршил ба хариуцагч' => [
            ['branch_id', 'Салбар', 'branch', ['required' => true]],
            ['unit', 'Нэгж', 'text', ['placeholder' => 'Жишээ: Захиргааны нэгж']],
            ['department', 'Алба', 'text', ['placeholder' => 'Жишээ: Мэдээллийн технологийн алба']],
            ['position', 'Албан тушаал', 'text', ['placeholder' => 'Жишээ: Ахлах мэргэжилтэн']],
            ['location', 'Байршил', 'text', ['placeholder' => 'Байр, давхар, өрөө']],
            ['holder_surname', 'Овог', 'text', ['placeholder' => 'Хариуцагчийн овог']],
            ['holder_name', 'Нэр', 'text', ['placeholder' => 'Хариуцагчийн нэр', 'hint' => 'Салбарын ажилтны нэртэй таарвал төхөөрөмж тэр ажилтанд автоматаар олгогдоно.']],
        ],
        'Худалдан авалт ба баталгаа' => [
            ['purchase_date', 'Худалдан авсан огноо', 'date', []],
            ['purchase_price', 'Үнэ (₮)', 'text', ['placeholder' => 'Жишээ: 2,500,000', 'inputmode' => 'decimal']],
            // Баталгааны хугацаа сонгоход «Баталгаа дуусах огноо» = худалдан авсан огноо + хугацаа (автоматаар)
            ['warranty_months', 'Баталгааны хугацаа', 'warranty', []],
            ['warranty_until', 'Баталгаа дуусах огноо', 'date', []],
        ],
    ];
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    @if (! $isEdit)
        @php
            // Шинээр бүртгэх төхөөрөмжийн төрлийн таб: анхдагчаар өмнө сонгосон / ?category= / Процессор
            $pickedCategories = array_values(array_filter((array) old('categories', array_filter([$val('category')]))));
            if (! $pickedCategories) {
                $fromQuery = (string) request('category');
                $pickedCategories = [match (true) {
                    array_key_exists($fromQuery, $categories) => $fromQuery,
                    filled($computer->kit_parent_id) => 'monitor',
                    $val('type') === 'laptop' => 'laptop',
                    default => 'processor',
                }];
            }
        @endphp
        <nav id="category-tabs" role="tablist" aria-label="Төхөөрөмжийн төрөл"
             class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
            @foreach (\App\Enums\EquipmentCategory::cases() as $case)
                <button type="button" role="tab" data-tab="{{ $case->value }}"
                        class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl !border-0 !bg-transparent px-3.5 py-2 text-sm font-medium !text-slate-600 hover:!bg-slate-100">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $case->icon() }}"/></svg>
                    {{ $case->label() }}
                </button>
            @endforeach
        </nav>

        @if (! empty($duplicate))
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                ⚠ Энэ серийн дугаартай (<b>{{ $duplicate->serial_number }}</b>) төхөөрөмж аль хэдийн бүртгэгдсэн байна:
                @if ($duplicate->trashed())
                    «{{ $duplicate->name }}» ({{ $duplicate->inventory_number }}) — <a href="{{ route('computers.trash') }}" class="font-semibold underline">Устгасан</a> хэсэгт байна, сэргээж болно.
                @else
                    <a href="{{ route('computers.show', $duplicate) }}" class="font-semibold underline">«{{ $duplicate->name }}» ({{ $duplicate->inventory_number }})</a>.
                    Дахин бүртгэх биш бол түүнийг засна уу.
                @endif
            </div>
        @endif

        {{-- «Хадгалаад дараагийнхыг бүртгэх»-ээр ирсэн үед юуг хуулсныг сануулна --}}
        @if (! empty($copiedFrom))
            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm text-blue-800">
                «{{ $copiedFrom->name }}» ({{ $copiedFrom->sequenceLabel() }})-ээс нийтлэг мэдээллийг хуулсан. Серийн дугаар, IP, MAC-г шинээр оруулна.
            </div>
        @endif
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            Мэдээллээ шалгана уу: {{ $errors->count() }} талбарт алдаа байна.
        </div>
    @endif

    {{-- Хариуцагчийг «Ажилтанд олгох» үйлдлээр онооно; энд одоогийн утгыг хэвээр хадгална --}}
    <input type="hidden" id="assigned_user_id" name="assigned_user_id" value="{{ old('assigned_user_id', $computer->assigned_user_id) }}">
    @error('user_id')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror

    @foreach ($sections as $title => $fields)
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                 @if (in_array($title, ['Техникийн үзүүлэлт', 'Сүлжээ'], true)) data-per-item @endif>
            <h2 class="mb-4 text-base font-bold text-slate-900">{{ $title }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($fields as [$name, $text, $kind, $o])
                    <div class="{{ ($kind === 'category' && ! $isEdit) ? 'md:col-span-2' : '' }}" @if ($kind === 'type') data-type-field @endif
                         @if (in_array($name, \App\Enums\EquipmentCategory::ALL_SPEC_FIELDS, true)) data-spec-field="{{ $name }}" @endif>
                        @php $auto = in_array($name, $detectedFields, true); @endphp
                        <label for="{{ $name }}" class="{{ $lb }}">
                            <span @if ($name === 'name' && ! $isEdit) id="name-label" @endif>{{ $text }}</span> @if (! empty($o['required']))<span class="text-rose-500">*</span>@endif
                            {{-- «Бусад» табд нэр заавал (JS тохируулна) --}}
                            @if ($name === 'name' && ! $isEdit)<span id="name-required" class="hidden text-rose-500">*</span>@endif
                            @if ($auto || ! empty($o['readonly']))<span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">авто</span>@endif
                        </label>

                        @if ($kind === 'status')
                            <select id="{{ $name }}" name="{{ $name }}" class="{{ $in }}">
                                @foreach ($statuses as $k => $v)
                                    <option value="{{ $k }}" @selected((string) $val($name) === (string) $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        @elseif ($kind === 'type')
                            <select id="{{ $name }}" name="{{ $name }}" class="{{ $in }} @if ($auto) !border-emerald-400 bg-emerald-50/40 @endif">
                                @php $cur = (string) ($val($name) ?: 'desktop'); @endphp
                                @unless (array_key_exists($cur, $types))
                                    <option value="{{ $cur }}" selected>{{ $cur }}</option>
                                @endunless
                                @foreach ($types as $k => $v)
                                    <option value="{{ $k }}" @selected($cur === (string) $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        @elseif ($kind === 'category' && $isEdit)
                            {{-- Засах үед: нэг төхөөрөмж = нэг ангилал --}}
                            <select id="{{ $name }}" name="{{ $name }}" class="{{ $in }}">
                                <option value="">Автоматаар (нэрээс нь таних)</option>
                                @foreach ($categories as $k => $v)
                                    <option value="{{ $k }}" @selected((string) $val($name) === (string) $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Хоосон орхивол нэрнээс нь таниж ангилна (жишээ: mouse, keyboard, монитор, printer).</p>
                        @elseif ($kind === 'category')
                            {{-- Нэмэх үед: нэг дор олныг сонгоно. Ангилал бүрээр тусдаа төхөөрөмж бүртгэгдэнэ --}}
                            {{-- Дээрх табаар нэгийг сонгоно; хэд хэдэн төхөөрөмжийг нэг дор бүртгэх бол энд нэмж сонгоно --}}
                            <details id="category-multi" class="mt-1 rounded-xl border border-slate-300 bg-white shadow-sm" @if (count($pickedCategories) > 1) open @endif>
                            <summary class="cursor-pointer select-none px-3 py-2.5 text-sm text-slate-700">
                                <span id="category-current" class="font-semibold"></span>
                                <span class="ml-2 text-xs font-medium text-blue-600">+ Хэд хэдэн төхөөрөмж нэг дор бүртгэх</span>
                            </summary>
                            <div id="category-box" class="border-t border-slate-200 p-3">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                                    <span>Хамт бүртгэх төхөөрөмжүүдээ сонгоно (жишээ нь компьютер + дэлгэц + mouse)</span>
                                    <span class="flex gap-3">
                                        <button type="button" id="category-core" class="!border-0 !bg-transparent !p-0 !text-xs font-medium text-blue-600 hover:underline">Үндсэн 5-г сонгох</button>
                                        <button type="button" id="category-clear" class="!border-0 !bg-transparent !p-0 !text-xs font-medium text-slate-500 hover:underline">Цэвэрлэх</button>
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                    @foreach ($categories as $k => $v)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                            <input type="checkbox" name="categories[]" value="{{ $k }}" class="h-4 w-4"
                                                   @checked(in_array((string) $k, array_map('strval', $pickedCategories), true))>
                                            {{ $v }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            </details>
                            @if (! empty($detectedDevices))
                                {{-- Скрипт энэ компьютерт холбогдсон дэлгэц, printer-ийг олсон --}}
                                <div id="detected-devices" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                    <span class="min-w-0 flex-1">
                                        Энэ компьютерт холбогдсон <b>{{ count($detectedDevices) }}</b> төхөөрөмж олдлоо:
                                        @foreach ($detectedDevices as $d)
                                            <span class="whitespace-nowrap">{{ \App\Enums\EquipmentCategory::from($d['category'])->label() }} {{ trim(($d['brand'] ?? '').' '.($d['model'] ?? '')) }}@if (! $loop->last);@endif</span>
                                        @endforeach
                                    </span>
                                    <button type="button" id="detected-add"
                                            class="rounded-lg !border-emerald-600 !bg-emerald-600 px-4 py-2 text-sm font-semibold !text-white hover:!bg-emerald-700">
                                        Хамт бүртгэх
                                    </button>
                                </div>
                            @endif
                            <p id="multi-hint" class="mt-3 hidden rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                Төхөөрөмж бүр тусдаа бүртгэгдэнэ. Доорх карт бүрт үзүүлэлтийг нь оруулна: жагсаалтаас сонгох эсвэл гараар бичнэ, мэдэхгүй бол хоосон орхиж болно.
                                Бүртгэлийн дугаар автоматаар үүснэ. Нэг ангиллын хэд хэдэн төхөөрөмж (жишээ нь 2 дэлгэц) бол «+ Дахин нэмэх» дарна.
                            </p>
                            {{-- Төхөөрөмж бүрийн картууд (JS үүсгэнэ) --}}
                            <div id="items-box" class="mt-3 hidden space-y-3"></div>
                            @error('categories')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @php $itemErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'items'))->flatten()->unique(); @endphp
                            @if ($itemErrors->isNotEmpty())
                                <ul class="mt-2 list-disc space-y-0.5 rounded-lg bg-rose-50 px-6 py-2 text-xs text-rose-700">
                                    @foreach ($itemErrors as $msg)<li>{{ $msg }}</li>@endforeach
                                </ul>
                            @endif
                        @elseif ($kind === 'warranty')
                            @php
                                // Засах үед: одоо байгаа огнооноос хугацааг таньж сонгоно; шинэ бүртгэлд 1 жил
                                $wm = old('warranty_months');
                                if ($wm === null) {
                                    $pd = $computer->purchase_date;
                                    $wu = $computer->warranty_until;
                                    $wm = ! $isEdit && ! $wu ? '12' : (! $wu ? '' : 'manual');
                                    foreach (\App\Support\Warranty::OPTIONS as $months => $_) {
                                        if ($pd && $wu && $pd->copy()->addMonthsNoOverflow($months)->isSameDay($wu)) {
                                            $wm = (string) $months;
                                        }
                                    }
                                }
                            @endphp
                            <select id="warranty_months" name="warranty_months" class="{{ $in }}">
                                <option value="" @selected($wm === '')>Баталгаагүй</option>
                                @foreach (\App\Support\Warranty::OPTIONS as $months => $text)
                                    <option value="{{ $months }}" @selected((string) $wm === (string) $months)>{{ $text }}</option>
                                @endforeach
                                <option value="manual" @selected($wm === 'manual')>Өөр (огноог гараар оруулна)</option>
                            </select>
                        @elseif ($kind === 'branch')
                            {{-- Салбар: Супер админ сонгоно, Админд өөрийн салбар (өөрчлөх боломжгүй) --}}
                            @php $me = auth()->user(); @endphp
                            @if ($me->isBranchScoped())
                                <div class="{{ $in }} bg-slate-50 text-slate-700">{{ $me->branch?->name ?? 'Салбар оноогоогүй' }}</div>
                                <p class="mt-1 text-xs text-slate-400">Таны хариуцах салбар. Өөр салбарт бүртгэх бол Супер админд хандана.</p>
                            @else
                                @php
                                    $branchList = \App\Models\Branch::query()->orderBy('name')->get(['id', 'name', 'code']);
                                    $branchValue = (string) old('branch_id', $computer->branch_id ?? ($branchList->count() === 1 ? $branchList->first()->id : ''));
                                @endphp
                                <select id="branch_id" name="branch_id" required class="{{ $in }} @error('branch_id') border-rose-400 @enderror">
                                    <option value="">— Салбар сонгох —</option>
                                    @foreach ($branchList as $b)
                                        <option value="{{ $b->id }}" @selected($branchValue === (string) $b->id)>{{ $b->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-400">Энэ салбарын Админ уг төхөөрөмжийг харж удирдана.</p>
                            @endif
                        @elseif ($kind === 'kit')
                            {{-- Дэлгэц, mouse зэргийг байгаа компьютерийн иж бүрдэлд нэмэх: дугаар нь PC-0012-3 гэх мэт --}}
                            @php $kitValue = (string) old('kit_parent_id', $computer->kit_parent_id); @endphp
                            <select id="kit_parent_id" name="kit_parent_id" class="{{ $in }}">
                                <option value="">— Тусдаа төхөөрөмж —</option>
                                @foreach ($kitParents ?? [] as $p)
                                    <option value="{{ $p->id }}" data-next="{{ $p->kitBase() }}-{{ max(1, (int) $p->kit_last) + 1 }}" data-main="{{ $p->kit_index ? $p->inventory_number : $p->inventory_number.'-1' }}" @selected($kitValue === (string) $p->id)>
                                        {{ $p->inventory_number }} · {{ $p->name }}@if ($p->assigned_to) ({{ $p->assigned_to }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <p id="kit-hint" class="mt-1 text-xs text-slate-400">Компьютерийн дагалдах хэрэгсэл бол сонгоно: дугаар нь компьютерийн дугаар + дэд дугаар болно.</p>
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $kind }}"
                                   value="{{ $val($name) }}" placeholder="{{ $o['placeholder'] ?? '' }}"
                                   @if ($kind === 'number') min="0" step="1" @endif
                                   @if (! empty($o['inputmode'])) inputmode="{{ $o['inputmode'] }}" @endif
                                   @if (isset($suggest[$name])) list="{{ $name }}-options" autocomplete="off" @endif
                                   @required(! empty($o['required']))
                                   @if (! empty($o['readonly'])) readonly tabindex="-1" @endif
                                   class="{{ $in }} @if ($auto) !border-emerald-400 bg-emerald-50/40 @endif @if (! empty($o['readonly'])) cursor-not-allowed !bg-slate-50 font-mono placeholder:!text-slate-700 @endif @error($name) border-rose-400 @enderror">
                            @if (isset($suggest[$name]))
                                {{-- Жагсаалтаас сонгох эсвэл гараар бичих --}}
                                <datalist id="{{ $name }}-options">
                                    @foreach ($suggest[$name] as $opt)
                                        <option value="{{ $opt }}"></option>
                                    @endforeach
                                </datalist>
                            @endif
                            @if ($name === 'ip_address')
                                {{-- Энэ хуудсыг нээсэн компьютерийн дотоод сүлжээний IP, MAC-г серверээс авна --}}
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button type="button" id="network-detect" data-url="{{ route('computers.network-info') }}"
                                            class="!rounded-lg !border-blue-300 !bg-blue-50 !px-3 !py-1.5 !text-xs font-semibold !text-blue-700 hover:!bg-blue-100">
                                        📡 IP, MAC автоматаар авах
                                    </button>
                                    <span id="network-status" class="text-xs text-slate-500">Бүртгэж буй компьютер дээрээ энэ хуудсыг нээсэн бол дарна</span>
                                </div>
                            @endif
                        @endif

                        @if (! empty($o['hint']))<p class="mt-1 text-xs text-slate-500">{{ $o['hint'] }}</p>@endif
                        @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <label for="notes" class="{{ $lb }}">Тэмдэглэл</label>
        <textarea id="notes" name="notes" rows="4" placeholder="Нэмэлт мэдээлэл..." class="{{ $in }}">{{ $val('notes') }}</textarea>
        @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </section>

    {{-- Үйлдлийн товчнууд --}}
    <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
        <a href="{{ url('/computers') }}"
           class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Цуцлах</a>
        @unless ($isEdit)
            {{-- Хадгалаад ижил мэдээлэлтэй шинэ форм нээнэ (олон компьютерийг дараалан бүртгэхэд) --}}
            <button type="submit" name="save_and_new" value="1" id="save-and-new"
                    class="rounded-xl border border-blue-600 bg-white px-5 py-2.5 text-sm font-semibold !text-blue-700 hover:bg-blue-50">
                Хадгалаад дараагийнхыг бүртгэх
            </button>
        @endunless
        <button type="submit"
                class="rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            {{ $isEdit ? 'Өөрчлөлтийг хадгалах' : 'Хадгалах' }}
        </button>
    </div>
</div>

@include('computers._spec_datalists')

<script>
// Ангилалд хамааралгүй техникийн талбарыг нууна (жишээ нь дэлгэц, mouse-д CPU, RAM, ҮС).
// Утга бөглөгдсөн талбар үргэлж харагдана. Сүлжээний талбар бүгд нуугдвал «Сүлжээ» хэсгийг нууна.
(function () {
    const specFields = @json(collect(\App\Enums\EquipmentCategory::cases())->mapWithKeys(fn ($c) => [$c->value => \App\Enums\EquipmentCategory::specFieldsFor($c)]));
    const select = document.querySelector('select[name="category"]');
    const boxes = Array.from(document.querySelectorAll('input[name="categories[]"]'));

    function currentCategory() {
        if (select) return select.value;
        const picked = boxes.filter(function (b) { return b.checked; });
        return picked.length === 1 ? picked[0].value : '';
    }

    const specsInput = document.getElementById('specs');
    const specsPlaceholders = @json(\App\Support\SpecSuggestions::SPECS_PLACEHOLDER);
    const groupOf = @json(\App\Support\SpecSuggestions::itemConfig()['groupOf']);

    function apply() {
        const cat = currentCategory();
        const allowed = specFields[cat] || null; // ангилалгүй бол бүгд
        // «Үзүүлэлт»-ийн сонголтын жагсаалт ангиллаас хамаарна (дэлгэц: 24 инч ..., UPS: 1000VA ...)
        if (specsInput) {
            cat && document.getElementById('sg-specs-' + cat) ? specsInput.setAttribute('list', 'sg-specs-' + cat) : specsInput.removeAttribute('list');
            if (specsPlaceholders[cat]) specsInput.placeholder = specsPlaceholders[cat];
        }
        // Үйлдвэрлэгч, загварын жагсаалт: компьютерт компьютерийн брэнд, бусад ангилалд тухайн ангиллынх (дэлгэц: LG, AOC ...)
        const nonComputer = cat && groupOf[cat] && groupOf[cat] !== 'computer' && document.getElementById('sg-brand-' + cat);
        const brandInput = document.getElementById('brand');
        const modelInput = document.getElementById('model');
        if (brandInput) brandInput.setAttribute('list', nonComputer ? 'sg-brand-' + cat : 'brand-options');
        if (modelInput) modelInput.setAttribute('list', nonComputer ? 'sg-model-' + cat : 'model-options');
        // Шинээр бүртгэхэд: хамааралгүй талбарыг нууж, илгээхгүй (Процессор табд бичсэн CPU, RAM нь Дэлгэц табд
        // шилжихэд дэлгэцэд хадгалагдахгүй; буцаж шилжвэл утга нь хэвээр). Олон төхөөрөмжийн горимд картууд удирдана.
        // Засахад: утгатай талбар үргэлж харагдана (хуучин бүртгэлийн мэдээлэл алга болохгүй).
        const creating = !select;
        const multi = creating && boxes.filter(function (b) { return b.checked; }).length > 1;
        document.querySelectorAll('[data-spec-field]').forEach(function (wrap) {
            const input = wrap.querySelector('input, select');
            const relevant = !allowed || allowed.indexOf(wrap.dataset.specField) !== -1;
            const show = relevant || (!creating && input && input.value.trim() !== '');
            wrap.classList.toggle('hidden', !show);
            if (creating && !multi && input) input.disabled = !relevant;
        });
        document.querySelectorAll('section[data-per-item]').forEach(function (section) {
            const fields = section.querySelectorAll('[data-spec-field]');
            const anyVisible = Array.from(fields).some(function (f) { return !f.classList.contains('hidden'); });
            section.style.display = anyVisible ? '' : 'none';
        });
    }

    // Олон төхөөрөмжийн скрипт (update) талбаруудын disabled-ийг өөрчилсний дараа дахин дуудна
    window.applySpecFields = apply;
    if (select) select.addEventListener('change', apply);
    boxes.forEach(function (b) { b.addEventListener('change', apply); });
    // «Үндсэн 5-г сонгох», «Цэвэрлэх», картыг хасах зэрэг товч checkbox-ыг кодоор өөрчилдөг
    const categoryBox = document.getElementById('category-box');
    if (categoryBox) categoryBox.addEventListener('click', function () { setTimeout(apply, 0); });
    apply();
})();
</script>

@include('computers._warranty_script', ['purchaseId' => 'purchase_date', 'monthsId' => 'warranty_months', 'untilId' => 'warranty_until'])

<script>
// «IP, MAC автоматаар авах»: энэ хуудсыг нээсэн компьютерийн дотоод сүлжээний IP, MAC-г серверээс авч бөглөнө
(function () {
    const button = document.getElementById('network-detect');
    if (!button) return;
    const status = document.getElementById('network-status');
    const ip = document.getElementById('ip_address');
    const mac = document.getElementById('mac_address');
    const say = function (text, cls) { status.textContent = text; status.className = 'text-xs ' + cls; };

    button.addEventListener('click', function () {
        button.disabled = true;
        say('Сүлжээг шалгаж байна…', 'text-slate-500');
        fetch(button.dataset.url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
            .then(function (d) {
                if (!d.ip) {
                    say('IP олдсонгүй. Компьютер дотоод сүлжээнд залгагдсан эсэхийг шалгаад дахин оролдоно уу.', 'text-rose-600');
                    return;
                }
                // Гараар бичсэн өөр утгыг асуулгүй дарж бичихгүй
                const replace = function (input, value) {
                    if (!input || !value || input.value === value) return;
                    if (input.value.trim() === '' || confirm(input.value + ' гэсэн утгыг ' + value + ' болгох уу?')) input.value = value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                };
                replace(ip, d.ip);
                replace(mac, d.mac);
                say('✓ IP ' + d.ip + (d.mac ? ', MAC ' + d.mac : ' (MAC олдсонгүй, гараар бичнэ үү)'), 'font-semibold text-emerald-700');
            })
            .catch(function () { say('Сүлжээний мэдээлэл авч чадсангүй.', 'text-rose-600'); })
            .finally(function () { button.disabled = false; });
    });
})();
</script>

<script>
// Үйлдвэрлэгчийг сонгоход тухайн брэндийн загваруудыг эхэнд санал болгоно
(function () {
    const brand = document.getElementById('brand');
    const list = document.getElementById('model-options');
    if (!brand || !list) return;

    const byBrand = @json($modelsByBrand);
    const all = @json($suggest['model']);

    function refresh() {
        const key = Object.keys(byBrand).find(function (b) {
            return b.toLowerCase() === brand.value.trim().toLowerCase();
        });
        const first = key ? byBrand[key] : [];
        const items = first.concat(all.filter(function (m) { return first.indexOf(m) === -1; }));
        list.innerHTML = '';
        items.forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m;
            list.appendChild(opt);
        });
    }

    brand.addEventListener('input', refresh);
    brand.addEventListener('change', refresh);
    refresh();
})();
</script>

@unless ($isEdit)
<script>
(function () {
    const box = document.getElementById('category-box');
    if (!box) return;

    const form = box.closest('form');
    const boxes = Array.from(box.querySelectorAll('input[name="categories[]"]'));
    const hint = document.getElementById('multi-hint');
    const inventory = document.getElementById('inventory_number');
    const perItem = document.querySelectorAll('[data-per-item]');
    const submit = form.querySelector('button[type="submit"]:not([name])');
    const saveAndNew = document.getElementById('save-and-new');
    const typeSelect = document.getElementById('type');

    // Бүртгэлийн дугаарын урьдчилсан харагдац: PC-B{салбар}-A{админ}-U{хариуцагч}-{огноо}-{өдрийн дугаар}
    const nextSerials = @json($nextSerials ?? []);
    const inventoryDate = @json($inventoryDate ?? now()->format('Ymd'));
    const myId = @json(auth()->id());
    const myBranchId = @json(auth()->user()->isBranchScoped() ? auth()->user()->branch_id : null);
    const branchSelect = document.getElementById('branch_id');
    const holderSelect = document.getElementById('assigned_user_id');
    const categoryPrefixes = @json(\App\Models\Computer::INVENTORY_PREFIXES);
    const typePrefixes = { laptop: 'NB', server: 'SRV', other: 'EQ' };
    const kitSelect = document.getElementById('kit_parent_id');
    const kitHint = document.getElementById('kit-hint');
    function previewInventory() {
        if (!inventory) return;
        if (inventory.disabled) { inventory.placeholder = 'Төхөөрөмж бүрт автоматаар үүснэ'; return; }
        // Иж бүрдэлд нэмэх бол: PC-0012-3 гэх мэт дэд дугаар
        const kit = kitSelect && kitSelect.selectedOptions[0];
        if (kit && kit.value) {
            inventory.placeholder = kit.dataset.next;
            if (kitHint) kitHint.textContent = 'Дагалдах хэрэгсэл болж ' + kit.dataset.next + ' дугаар авна (олон бол дараалан). Үндсэн компьютер: ' + kit.dataset.main + '.';
            return;
        }
        if (kitHint) kitHint.textContent = 'Компьютерийн дагалдах хэрэгсэл бол сонгоно: дугаар нь компьютерийн дугаар + дэд дугаар болно.';
        const picked = boxes.find(function (b) { return b.checked; });
        const prefix = picked ? (categoryPrefixes[picked.value] || 'EQ') : (typePrefixes[typeSelect && typeSelect.value] || 'PC');
        const branch = (branchSelect ? branchSelect.value : myBranchId) || 0;
        const holder = (holderSelect && holderSelect.value) || 0;
        const serial = String(nextSerials[prefix] || 1).padStart(3, '0');
        inventory.placeholder = prefix + '-B' + branch + '-A' + myId + '-U' + holder + '-' + inventoryDate + '-' + serial;
    }
    if (typeSelect) typeSelect.addEventListener('change', previewInventory);
    if (branchSelect) branchSelect.addEventListener('change', previewInventory);
    if (kitSelect) kitSelect.addEventListener('change', previewInventory);
    const submitText = submit ? submit.textContent.trim() : '';
    const singleAction = form.getAttribute('action');
    const multiAction = @json(route('computers.store-multi'));
    const core = ['processor', 'monitor', 'mouse', 'keyboard', 'printer'];

    // ── Төхөөрөмж бүрийн картууд ──
    const itemsBox = document.getElementById('items-box');
    const cfg = @json($itemConfig);
    const categoryLabels = @json($categories);
    const detectedType = @json(in_array('type', $detectedFields, true) ? $val('type') : null);
    const detectedDevices = @json($detectedDevices ?? []);
    const oldItems = @json(array_values((array) old('items', [])));
    const inputClass = @json($in);
    let seq = 0;
    let wasMulti = false;

    function cards() { return Array.from(itemsBox.querySelectorAll('[data-card]')); }
    function cardsOf(cat) { return cards().filter(function (c) { return c.dataset.category === cat; }); }
    function fieldsOf(cat) { return cfg.groups[cfg.groupOf[cat] || 'basic']; }
    function mainValue(f) { const el = document.getElementById(f); return el ? el.value : ''; }
    function isEmpty(card) {
        return Array.from(card.querySelectorAll('input')).every(function (i) { return i.value.trim() === ''; });
    }
    // Үндсэн хэсэгт бичсэн (эсвэл скриптээр уншсан) утгыг картанд хуулна
    function fillFromMain(card) {
        card.querySelectorAll('input[data-field]').forEach(function (i) {
            if (!i.value) i.value = mainValue(i.dataset.field);
        });
    }

    function listFor(cat, f) {
        const computer = (cfg.groupOf[cat] === 'computer');
        if (f === 'brand') return computer ? 'brand-options' : 'sg-brand-' + cat;
        if (f === 'model') return computer ? 'model-options' : 'sg-model-' + cat;
        if (f === 'specs') return 'sg-specs-' + cat;
        return ['cpu', 'ram_gb', 'storage', 'os'].indexOf(f) !== -1 ? f + '-options' : '';
    }

    // after: энэ картын ард оруулна (ижил ангиллын картууд дараалан байна: Дэлгэц 1, Дэлгэц 2, Mouse)
    function addCard(cat, values, auto, after) {
        const idx = seq++;
        const card = document.createElement('div');
        card.dataset.card = '';
        card.dataset.category = cat;
        card.className = 'rounded-xl border p-4 ' + (auto ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/60');

        const head = document.createElement('div');
        head.className = 'mb-3 flex flex-wrap items-center justify-between gap-2';
        const title = document.createElement('span');
        title.className = 'text-sm font-semibold text-slate-800';
        title.dataset.title = '';
        const actions = document.createElement('span');
        actions.className = 'flex gap-4';
        actions.innerHTML =
            '<button type="button" data-dup class="!border-0 !bg-transparent !p-0 !text-xs font-medium text-blue-600 hover:underline">+ Дахин нэмэх</button>' +
            '<button type="button" data-remove class="!border-0 !bg-transparent !p-0 !text-xs font-medium text-rose-600 hover:underline">Хасах</button>';
        head.append(title, actions);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'items[' + idx + '][category]';
        hidden.value = cat;

        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3';
        fieldsOf(cat).forEach(function (f) {
            const wrap = document.createElement('label');
            wrap.className = 'block text-xs font-medium text-slate-600';
            wrap.textContent = cfg.labels[f];
            const input = document.createElement('input');
            input.type = f === 'ram_gb' ? 'number' : 'text';
            if (f === 'ram_gb') { input.min = '1'; input.step = '1'; }
            input.name = 'items[' + idx + '][' + f + ']';
            input.dataset.field = f;
            input.autocomplete = 'off';
            input.className = inputClass;
            const list = listFor(cat, f);
            if (list) input.setAttribute('list', list);
            if (f === 'specs') input.placeholder = cfg.specsPlaceholder[cat] || '';
            if (f === 'brand') input.placeholder = 'Сонгох эсвэл бичих';
            if (f === 'mac_address') input.placeholder = 'AA:BB:CC:DD:EE:FF';
            if (values && values[f] !== undefined && values[f] !== null) input.value = values[f];
            wrap.appendChild(input);
            grid.appendChild(wrap);
        });

        card.append(head, hidden, grid);
        if (after) after.after(card); else itemsBox.appendChild(card);
        return card;
    }

    function syncCheckboxes() {
        boxes.forEach(function (b) { b.checked = cardsOf(b.value).length > 0; });
    }

    function update() {
        // Шалгасан ангилал бүрт дор хаяж нэг карт, шалгаагүйнх нь картыг хасна
        boxes.forEach(function (b) {
            if (b.checked && cardsOf(b.value).length === 0) {
                const card = addCard(b.value);
                if (cfg.groupOf[b.value] === 'computer' && cards().filter(function (c) { return cfg.groupOf[c.dataset.category] === 'computer'; }).length === 1) {
                    fillFromMain(card);
                }
            }
            if (!b.checked) cardsOf(b.value).forEach(function (c) { c.remove(); });
        });

        const all = cards();
        const multi = all.length > 1;

        // Нэгээс олон төхөөрөмж болоход: өмнө нь үндсэн хэсэгт бичсэн утгыг эхний хоосон картанд шилжүүлнэ
        if (multi && !wasMulti && all.length && isEmpty(all[0])) fillFromMain(all[0]);
        wasMulti = multi;

        // Картын гарчиг: «Дэлгэц 2» гэх мэт
        Object.keys(categoryLabels).forEach(function (cat) {
            const list = cardsOf(cat);
            list.forEach(function (c, i) {
                c.querySelector('[data-title]').textContent = categoryLabels[cat] + (list.length > 1 ? ' ' + (i + 1) : '');
            });
        });

        form.setAttribute('action', multi ? multiAction : singleAction);
        hint.classList.toggle('hidden', !multi);
        itemsBox.classList.toggle('hidden', !multi);
        itemsBox.querySelectorAll('input').forEach(function (el) { el.disabled = !multi; });

        // Олон төхөөрөмж: бүртгэлийн дугаар автоматаар үүснэ, үзүүлэлтийг карт бүрт оруулна
        if (inventory) {
            inventory.disabled = multi;
            inventory.required = false; // шинэ бүртгэлд дугаар үргэлж автоматаар үүснэ
        }
        perItem.forEach(function (section) {
            section.classList.toggle('hidden', multi);
            section.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = multi; });
        });
        // Үнэ төхөөрөмж бүрт өөр тул олон төхөөрөмж бүртгэхэд нийтлэг хэсэгт оруулахгүй (дараа нь тус бүр дээр засна)
        const price = document.getElementById('purchase_price');
        if (price) {
            price.disabled = multi;
            price.closest('div').classList.toggle('hidden', multi);
        }

        if (submit) submit.textContent = multi ? (all.length + ' төхөөрөмж бүртгэх') : submitText;
        if (saveAndNew) saveAndNew.hidden = multi; // олон төхөөрөмж бүртгэх үед хэрэггүй
        previewInventory();
        syncTabs();
        if (window.applySpecFields) window.applySpecFields();
    }

    itemsBox.addEventListener('click', function (e) {
        const card = e.target.closest('[data-card]');
        if (!card) return;
        if (e.target.closest('[data-dup]')) {
            const copy = {};
            // Ижил загварын (жишээ нь 2 ижил дэлгэц) үйлдвэрлэгч, загвар, үзүүлэлтийг хуулна, серийн дугаарыг биш
            ['brand', 'model', 'specs'].forEach(function (f) {
                const i = card.querySelector('[data-field="' + f + '"]');
                if (i) copy[f] = i.value;
            });
            const same = cardsOf(card.dataset.category);
            addCard(card.dataset.category, copy, false, same[same.length - 1]).querySelector('[data-field="serial_number"]').focus();
        } else if (e.target.closest('[data-remove]')) {
            card.remove();
            syncCheckboxes();
        } else {
            return;
        }
        update();
    });

    boxes.forEach(function (b) { b.addEventListener('change', update); });

    document.getElementById('category-core').addEventListener('click', function () {
        boxes.forEach(function (b) { b.checked = core.indexOf(b.value) !== -1; });
        update();
    });
    // «Цэвэрлэх»: зөвхөн идэвхтэй табын төхөөрөмж үлдэнэ
    document.getElementById('category-clear').addEventListener('click', function () {
        boxes.forEach(function (b) { b.checked = b.value === activeTab; });
        update();
    });

    // ── Төрлийн таб: Процессор, Нөүтбүк, Дэлгэц ... Таб дарахад тухайн ганц төхөөрөмжийн форм болно ──
    const tabs = Array.from(document.querySelectorAll('#category-tabs [data-tab]'));
    const typeField = document.querySelector('[data-type-field]');
    const current = document.getElementById('category-current');
    const multiBox = document.getElementById('category-multi');
    const nameLabel = document.getElementById('name-label');
    const nameRequired = document.getElementById('name-required');
    const nameInput = document.getElementById('name');
    let activeTab = (boxes.find(function (b) { return b.checked; }) || {}).value || 'processor';

    function syncTabs() {
        const picked = boxes.filter(function (b) { return b.checked; });
        if (picked.length && !picked.some(function (b) { return b.value === activeTab; })) activeTab = picked[0].value;
        tabs.forEach(function (t) {
            const on = t.dataset.tab === activeTab;
            const inSet = picked.some(function (b) { return b.value === t.dataset.tab; });
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.classList.toggle('!bg-blue-600', on);
            t.classList.toggle('!text-white', on);
            t.classList.toggle('hover:!bg-slate-100', !on);
            t.classList.toggle('!bg-transparent', !on && !inSet);
            t.classList.toggle('!bg-blue-50', !on && inSet); // хамт бүртгэх төхөөрөмж
            t.classList.toggle('!text-blue-700', !on && inSet);
            t.classList.toggle('!text-slate-600', !on && !inSet);
        });
        if (current) {
            current.textContent = picked.length > 1
                ? picked.map(function (b) { return categoryLabels[b.value]; }).join(' + ')
                : (categoryLabels[activeTab] || '');
        }
        // «Төрөл» (суурин, зөөврийн ...) зөвхөн компьютерт хамаарна
        if (typeField) typeField.classList.toggle('hidden', picked.length <= 1 && cfg.groupOf[activeTab] !== 'computer');

        // Нэр: компьютерт «Компьютерийн нэр», бусад төхөөрөмжид «Төхөөрөмжийн нэр».
        // «Бусад» табд юу болохыг заавал бичнэ (Проектор, Веб камер ...), эс бөгөөс «Бусад EQ-...» гэж үлдэнэ
        const single = picked.length <= 1;
        const isOther = single && activeTab === 'other';
        if (nameLabel) nameLabel.textContent = single && cfg.groupOf[activeTab] === 'computer' ? 'Компьютерийн нэр' : 'Төхөөрөмжийн нэр';
        if (nameRequired) nameRequired.classList.toggle('hidden', !isOther);
        if (nameInput) {
            nameInput.required = isOther && !nameInput.disabled;
            nameInput.placeholder = isOther ? 'Ямар төхөөрөмж вэ? Жишээ: Проектор, Веб камер, Чихэвч' : 'Хоосон бол: Үйлдвэрлэгч + Загвар';
        }
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            activeTab = t.dataset.tab;
            boxes.forEach(function (b) { b.checked = b.value === activeTab; });
            if (typeSelect) {
                if (activeTab === 'laptop') typeSelect.value = 'laptop';
                else if (activeTab === 'processor') { if (['laptop', 'other'].indexOf(typeSelect.value) !== -1) typeSelect.value = 'desktop'; }
                else typeSelect.value = 'other';
            }
            if (multiBox) multiBox.open = false;
            // change үйл явдлаар талбаруудыг (CPU, RAM ... ) ангилалд нь тааруулна
            const box = boxes.find(function (b) { return b.value === activeTab; });
            if (box) box.dispatchEvent(new Event('change'));
            const first = document.getElementById('brand');
            if (first && !first.disabled) first.focus({ preventScroll: true });
        });
    });

    // «Хамт бүртгэх»: компьютер + скриптийн олсон дэлгэц, printer-ийг картаар нэмнэ
    const detectedAdd = document.getElementById('detected-add');
    if (detectedAdd) {
        detectedAdd.addEventListener('click', function () {
            const pcCat = detectedType === 'laptop' ? 'laptop' : 'processor';
            if (cardsOf(pcCat).length === 0) fillFromMain(addCard(pcCat, null, true));
            detectedDevices.forEach(function (d) { addCard(d.category, d, true); });
            syncCheckboxes();
            update();
            document.getElementById('detected-devices').remove();
            itemsBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // Алдаатай буцсан бол өмнө оруулсан картуудыг сэргээнэ
    oldItems.forEach(function (it) { if (it && it.category) addCard(it.category, it); });
    if (oldItems.length) syncCheckboxes();

    // Компьютер биш таб (дэлгэц, mouse ...)-аар нээгдсэн бол төрөл нь «Бусад»
    if (typeSelect && boxes.filter(function (b) { return b.checked; }).length === 1 && cfg.groupOf[activeTab] !== 'computer') typeSelect.value = 'other';

    update();
})();
</script>
@endunless
