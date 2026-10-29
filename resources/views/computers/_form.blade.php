@php
    $computer = $computer ?? new \App\Models\Computer();
    $isEdit = $computer->exists;
    $detectedFields = $detected ?? []; // скриптээр автоматаар бөглөгдсөн талбарууд

    // Утгыг (enum, огноо, string) input-д тохирох текст болгох
    $val = function (string $f) use ($computer) {
        // RAM: модулиудтай бол «8GB + 4GB»
        $v = old($f, $f === 'ram_gb' ? $computer->ramInput() : $computer->{$f});
        if ($v instanceof \BackedEnum) return $v->value;
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        // Үнэ: «2500000.00» → «2,500,000»
        if ($f === 'purchase_price' && is_numeric($v)) return number_format((float) $v, fmod((float) $v, 1.0) == 0.0 ? 0 : 2, '.', ',');
        return $v;
    };

    $label = function ($case, array $map = []) {
        if (method_exists($case, 'label')) return $case->label();   
        return $map[$case->value] ?? $case->value;
    };

    $statusMap = [
        'active' => 'Ашиглагдаж байгаа', 'in_use' => 'Ашиглагдаж байгаа', 'repair' => 'Засварт', 'maintenance' => 'Засварт',
        'storage' => 'Агуулахад', 'stock' => 'Агуулахад', 'retired' => 'Актласан', 'disposed' => 'Актласан',
    ];
    $statuses = class_exists(\App\Enums\ComputerStatus::class)
        ? collect(\App\Enums\ComputerStatus::cases())->mapWithKeys(fn($c) => [$c->value => $label($c, $statusMap)])->all()
        : ['active' => 'Ашиглагдаж байгаа', 'repair' => 'Засварт', 'storage' => 'Агуулахад', 'retired' => 'Актласан'];

    $typeMap = [
        'desktop' => 'Суурин компьютер', 'laptop' => 'Зөөврийн компьютер', 'server' => 'Сервер',
        'all_in_one' => 'Бүгд нэг дор (All-in-one)', 'workstation' => 'Ажлын станц', 'tablet' => 'Таблет', 'other' => 'Бусад',
    ];
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
            // Иж бүрдэл: зөвхөн төхөөрөмжийн хуудасны «+ Дагалдах хэрэгсэл нэмэх»-ээр (?kit=) ирсэн үед харагдана
            ...(! $isEdit && filled(old('kit_parent_id', $computer->kit_parent_id)) ? [['kit_parent_id', 'Иж бүрдэл (дагалдах хэрэгсэл бол)', 'kit', []]] : []),
            ['status', 'Төлөв', 'status', []],
        ],
        'Техникийн үзүүлэлт' => [
            ['brand', 'Үйлдвэрлэгч', 'text', ['placeholder' => 'Сонгох эсвэл бичих: Dell, HP, Lenovo...']],
            ['model', 'Загвар', 'text', ['placeholder' => 'Сонгох эсвэл бичих']],
            ['serial_number', 'Серийн дугаар', 'text', []],
            ['cpu', 'Процессор (CPU)', 'text', ['placeholder' => 'Intel Core i5-12400']],
            ['ram_gb', 'RAM', 'ram', []],
            ['os', 'Үйлдлийн систем', 'text', ['placeholder' => 'Windows 11 Pro']],
            ['storage', 'Санах ой (диск)', 'storage', []],
            ['specs', 'Үзүүлэлт', 'text', ['placeholder' => 'Дэлгэцийн хэмжээ, printer-ийн төрөл гэх мэт']],
        ],
        'Байршил ба хариуцагч' => [
            ['branch_id', 'Салбар', 'branch', ['required' => true]],
            ['location', 'Байршил', 'text', ['placeholder' => 'Байр, давхар, өрөө']],
            ['holder_surname', 'Хариуцагчийн овог', 'text', ['placeholder' => 'Овог']],
            ['holder_name', 'Хариуцагчийн нэр', 'text', ['placeholder' => 'Нэр']],
            ['unit', 'Нэгж', 'text', ['placeholder' => 'Жишээ: Захиргааны нэгж']],
            ['department', 'Алба', 'department', []],
            ['position', 'Албан тушаал', 'text', ['placeholder' => 'Жишээ: Ахлах мэргэжилтэн']],
        ],
        'Худалдан авалт ба баталгаа' => [
            ['purchase_date', 'Худалдан авсан огноо', 'date', []],
            ['purchase_price', 'Үнэ (₮)', 'text', ['placeholder' => 'Жишээ: 2,500,000', 'inputmode' => 'decimal', 'money' => true]],
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
                {{-- Mouse, Keyboard, Сүлжээний төхөөрөмж нь «Дагалдах хэрэгсэл» табд (доорх дэд сонголт) --}}
                @continue(in_array($case->value, \App\Enums\EquipmentCategory::ACCESSORIES, true) && $case !== \App\Enums\EquipmentCategory::Other)
                <button type="button" role="tab" data-tab="{{ $case === \App\Enums\EquipmentCategory::Other ? 'accessory' : $case->value }}"
                        class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl !border-0 !bg-transparent px-3.5 py-2 text-sm font-medium !text-slate-600 hover:!bg-slate-100">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $case->icon() }}"/></svg>
                    {{ $case->label() }}
                </button>
            @endforeach
        </nav>
        {{-- «Дагалдах хэрэгсэл»-ийн дэд сонголт: Mouse, Keyboard, Сүлжээний төхөөрөмж, Бусад --}}
        <div id="accessory-tabs" role="tablist" aria-label="Дагалдах хэрэгсэл" class="-mt-3 hidden flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
            @foreach (\App\Enums\EquipmentCategory::ACCESSORIES as $value)
                @php $case = \App\Enums\EquipmentCategory::from($value); @endphp
                <button type="button" role="tab" data-tab="{{ $value }}"
                        class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl !border-0 !bg-transparent px-3.5 py-1.5 text-sm font-medium !text-slate-600 hover:!bg-slate-100">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $case->icon() }}"/></svg>
                    {{ $case === \App\Enums\EquipmentCategory::Other ? 'Бусад' : $case->label() }}
                </button>
            @endforeach
        </div>

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

    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-medium text-rose-700">
            Мэдээллээ шалгана уу: {{ $errors->count() }} талбарт алдаа байна.
        </div>
    @endif

    {{-- Хариуцагчийг «Ажилтанд олгох» үйлдлээр онооно; энд одоогийн утгыг хэвээр хадгална --}}
    @php $holderId = old('assigned_user_id', $computer->assigned_user_id); @endphp
    <input type="hidden" id="assigned_user_id" name="assigned_user_id" value="{{ $holderId }}" data-code-number="{{ \App\Models\User::codeNumber($holderId ? (int) $holderId : null) }}">
    @error('user_id')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror

    @foreach ($sections as $title => $fields)
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                @if ($title === 'Техникийн үзүүлэлт') data-per-item @endif>
            {{-- Хэсгийн гарчиг: дугаартай (1 Үндсэн мэдээлэл, 2 Техникийн үзүүлэлт ...) --}}
            <h2 class="!mb-0 flex items-center gap-3 border-b border-slate-100 px-6 py-4 text-base font-semibold text-slate-900">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-600">{{ $loop->iteration }}</span>
                {{ $title }}
            </h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 px-6 py-5 md:grid-cols-2">
                @foreach ($fields as [$name, $text, $kind, $o])
                    <div class="{{ (($kind === 'category' && ! $isEdit) || $kind === 'storage') ? 'md:col-span-2' : '' }}" @if ($kind === 'type') data-type-field @endif
                        @if (in_array($name, \App\Enums\EquipmentCategory::ALL_SPEC_FIELDS, true)) data-spec-field="{{ $name }}" @endif>
                        @php $auto = in_array($name, $detectedFields, true); @endphp
                        @if ($kind !== 'storage')
                        <label for="{{ $name }}" class="{{ $lb }}">
                            <span @if ($name === 'name' && ! $isEdit) id="name-label" @endif>{{ $text }}</span> @if (! empty($o['required']))<span class="text-rose-500">*</span>@endif
                            {{-- «Бусад» табд нэр заавал (JS тохируулна) --}}
                            @if ($name === 'name' && ! $isEdit)<span id="name-required" class="hidden text-rose-500">*</span>@endif
                            @if ($auto || ! empty($o['readonly']))<span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">авто</span>@endif
                        </label>
                        @endif

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
                                    <span>Хамт бүртгэх төхөөрөмжүүд</span>
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
                            {{-- Процессор бүртгэхэд mouse, keyboard иж бүрдлийн дагалдах хэрэгсэл болж хамт бүртгэгдэнэ (JS зөвхөн Процессор табд харуулна) --}}
                            @php $withPicked = old('_token') ? (array) old('with_accessories', []) : \App\Enums\EquipmentCategory::WITH_PROCESSOR_DEFAULT; @endphp
                            <div id="with-accessories" class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                <span class="font-medium text-slate-700">Хамт бүртгэх:</span>
                                @foreach (\App\Enums\EquipmentCategory::WITH_PROCESSOR as $value)
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="checkbox" name="with_accessories[]" value="{{ $value }}" class="h-4 w-4" @checked(in_array($value, $withPicked, true))>
                                        {{ \App\Enums\EquipmentCategory::from($value)->label() }}
                                    </label>
                                @endforeach
                            </div>
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
                        @elseif ($kind === 'storage')
                            {{-- SSD, HDD, M.2-г тусад нь бөглөнө; хадгалахад «512GB SSD + 1TB HDD + 256GB M.2» нэг мөр болно, доор нь нийт GB (доорх скрипт, ComputerRequest) --}}
                            @php $disk = \App\Support\SpecSuggestions::storageParts($val('storage')); @endphp
                            <input type="hidden" id="storage" name="storage" value="{{ $val('storage') }}">
                            <div data-storage-parts class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                @foreach (\App\Support\SpecSuggestions::DISK_KINDS as $part => $partLabel)
                                    <div>
                                        <label for="storage_{{ $part }}" class="{{ $lb }}">
                                            Санах ой — {{ $partLabel }}
                                            @if ($auto)<span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">авто</span>@endif
                                        </label>
                                        <input id="storage_{{ $part }}" name="storage_{{ $part }}" type="text" data-part="{{ $part }}"
                                                value="{{ old('storage_'.$part, $disk[$part]) }}" placeholder="{{ ['ssd' => 'Жишээ: 512GB', 'hdd' => 'Жишээ: 1TB', 'm2' => 'Жишээ: 256GB'][$part] }}"
                                                list="sg-storage-{{ $part }}" autocomplete="off"
                                                class="{{ $in }} @if ($auto) !border-emerald-400 bg-emerald-50/40 @endif @error($name) border-rose-400 @enderror">
                                    </div>
                                @endforeach
                                <p data-storage-total class="text-sm text-slate-500 md:col-span-3">Нийт: <b class="font-semibold text-slate-800">—</b></p>
                            </div>
                        @elseif ($kind === 'department')
                            {{-- Алба: «Алба хэлтэс» хүснэгтээс сонгоно (Супер админд сонгосон салбарынх л харагдана) --}}
                            @php
                                $deptValue = (string) $val('department');
                                $deptList = \App\Models\Department::query()
                                    ->when(auth()->user()->isBranchScoped(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
                                    ->orderBy('name')->get(['id', 'branch_id', 'name']);
                            @endphp
                            <select id="department" name="department" data-department-select class="{{ $in }} @error($name) border-rose-400 @enderror">
                                <option value="">— Алба сонгох —</option>
                                @foreach ($deptList as $d)
                                    <option value="{{ $d->name }}" data-branch="{{ $d->branch_id }}" @selected($deptValue === $d->name)>{{ $d->name }}</option>
                                @endforeach
                                {{-- Жагсаалтад байхгүй хуучин утга алга болохгүй --}}
                                @if ($deptValue !== '' && ! $deptList->contains('name', $deptValue))
                                    <option value="{{ $deptValue }}" selected>{{ $deptValue }}</option>
                                @endif
                                <option value="__new__">＋ Шинэ алба нэмэх…</option>
                            </select>
                            {{-- Жагсаалтад байхгүй бол нэрийг бичнэ: хадгалахад сонгосон салбарт алба үүснэ --}}
                            <div data-department-new hidden class="mt-1 flex gap-2">
                                <input type="text" id="department-new" name="department" maxlength="255" disabled class="{{ $in }} !mt-0 min-w-0 flex-1" placeholder="Шинэ албаны нэр" autocomplete="off">
                                <button type="button" data-department-cancel title="Жагсаалтаас сонгох" aria-label="Жагсаалтаас сонгох"
                                        class="flex !min-h-0 shrink-0 items-center !rounded-xl !border !border-slate-300 !bg-white !px-3 !py-0 !text-slate-500 hover:!bg-slate-50">✕</button>
                            </div>
                        @elseif ($kind === 'ram')
                            {{-- RAM: модуль бүрийг тусад нь бичиж «+» дарж нэмнэ; хадгалахад «8GB + 4GB» нэг мөр болно (доорх скрипт) --}}
                            <input type="hidden" id="ram_gb" name="ram_gb" value="{{ $val('ram_gb') }}">
                            <div data-ram-modules class="@if ($auto) !border-emerald-400 !bg-emerald-50/40 @endif @error($name) !border-rose-400 @enderror"></div>
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $kind }}"
                                    value="{{ $val($name) }}" placeholder="{{ $o['placeholder'] ?? '' }}"
                                    @if ($kind === 'number') min="0" step="1" @endif
                                    @if (! empty($o['inputmode'])) inputmode="{{ $o['inputmode'] }}" @endif
                                    @if (! empty($o['money'])) data-money autocomplete="off" @endif
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
                        @endif

                        @if (! empty($o['hint']))<p class="mt-1 text-xs text-slate-500">{{ $o['hint'] }}</p>@endif
                        @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <h2 class="!mb-0 flex items-center gap-3 border-b border-slate-100 px-6 py-4 text-base font-semibold text-slate-900">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
            </span>
            <label for="notes" class="!m-0">Тэмдэглэл <span class="text-sm font-normal text-slate-400">(заавал биш)</span></label>
        </h2>
        <div class="p-6">
            <textarea id="notes" name="notes" rows="4" placeholder="Нэмэлт мэдээлэл..." class="{{ $in }} !mt-0">{{ $val('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </section>

    {{-- Үйлдлийн товчнууд --}}
    <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
        <a href="{{ url('/computers') }}"
            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold !text-slate-700 transition hover:bg-slate-100">Цуцлах</a>
        @unless ($isEdit)
            {{-- Хадгалаад ижил мэдээлэлтэй шинэ форм нээнэ (олон компьютерийг дараалан бүртгэхэд) --}}
            <button type="submit" name="save_and_new" value="1" id="save-and-new"
                    class="inline-flex h-10 !min-h-0 items-center !rounded-lg !border-blue-600 !bg-white !px-4 !py-0 text-sm font-semibold !text-blue-700 transition hover:!bg-blue-50">
                Хадгалаад дараагийнх
            </button>
        @endunless
        <button type="submit"
                class="inline-flex h-10 !min-h-0 items-center !rounded-lg !border-blue-600 !bg-blue-600 !px-6 !py-0 text-sm font-semibold !text-white shadow-sm transition hover:!bg-blue-700">
            {{ $isEdit ? 'Өөрчлөлтийг хадгалах' : 'Хадгалах' }}
        </button>
    </div>
</div>

@include('computers._spec_datalists')

<script>
// Ангилалд хамааралгүй техникийн талбарыг нууна (жишээ нь дэлгэц, mouse-д CPU, RAM, ҮС).
// Утга бөглөгдсөн талбар үргэлж харагдана. Талбар бүгд нуугдсан хэсгийг нууна.
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
            // Диск нь SSD, HDD, нуугдсан нэгтгэл гэсэн гурван талбартай
            const inputs = Array.from(wrap.querySelectorAll('input, select'));
            const relevant = !allowed || allowed.indexOf(wrap.dataset.specField) !== -1;
            const show = relevant || (!creating && inputs.some(function (i) { return i.value.trim() !== ''; }));
            wrap.classList.toggle('hidden', !show);
            if (creating && !multi) inputs.forEach(function (i) { i.disabled = !relevant; });
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
// Алба: сонгосон салбарын хэлтсүүд л (Супер админ салбар солиход); өөр салбарынх сонгогдсон бол арилгана
(function () {
    const select = document.querySelector('[data-department-select]');
    const branch = document.getElementById('branch_id');
    if (!select) return;

    // «＋ Шинэ алба нэмэх…»: жагсаалтын оронд бичих талбар (зөвхөн аль нэг нь илгээгдэнэ)
    const box = document.querySelector('[data-department-new]');
    const input = box.querySelector('input');
    function typing(on) {
        select.hidden = select.disabled = on;
        box.hidden = !on;
        input.disabled = !on;
        if (on) input.focus(); else { input.value = ''; select.value = ''; }
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    select.addEventListener('change', function () { if (select.value === '__new__') typing(true); });
    box.querySelector('[data-department-cancel]').addEventListener('click', function () { typing(false); });

    if (!branch) return;
    function filter() {
        Array.from(select.options).forEach(function (o) {
            if (!o.dataset.branch) return; // «— Алба сонгох —», хуучин утга
            o.hidden = o.disabled = !!branch.value && o.dataset.branch !== branch.value;
        });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
    }
    branch.addEventListener('change', filter);
    filter();
})();
</script>

<script>
// Хариуцагч, алба хэлтсийн үсгийн алдаа: бичих явцад «ажилтан олдлоо / шинэ ажилтан үүснэ» гэж харуулж, ойролцоо
// нэрийг санал болгоно («Enhee» → «Энхээ»: латин, кирилл ялгаагүй). Хадгалахад шинэ ажилтан үүсэх бол баталгаажуулна.
(function () {
    const nameInput = document.getElementById('holder_name');
    const surnameInput = document.getElementById('holder_surname');
    const deptSelect = document.getElementById('department');
    const deptNew = document.getElementById('department-new');
    const assigned = document.getElementById('assigned_user_id');
    const branchSelect = document.getElementById('branch_id');
    const myBranchId = @json(auth()->user()->isBranchScoped() ? auth()->user()->branch_id : null);
    const people = @json($holderCandidates ?? []);
    const departments = @json($suggest['department'] ?? []);
    if (!nameInput) return;

    // Кирилл → латин, жижиг үсгээр (харьцуулах түлхүүр)
    const CYR = { 'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo', 'ж': 'j', 'з': 'z', 'и': 'i', 'й': 'i',
        'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'ө': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ү': 'u',
        'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sh', 'ъ': '', 'ы': 'y', 'ь': 'i', 'э': 'e', 'ю': 'yu', 'я': 'ya' };
    function key(s) {
        let out = '';
        for (const ch of String(s || '').toLowerCase().trim()) out += CYR[ch] !== undefined ? CYR[ch] : ch;
        return out.replace(/kh/g, 'h').replace(/ö/g, 'o').replace(/ü/g, 'u').replace(/[^a-z0-9]/g, '');
    }
    function distance(a, b) {
        const row = Array.from({ length: b.length + 1 }, function (_, i) { return i; });
        for (let i = 1; i <= a.length; i++) {
            let prev = row[0];
            row[0] = i;
            for (let j = 1; j <= b.length; j++) {
                const cur = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
                prev = cur;
            }
        }
        return row[b.length];
    }
    // Ойролцоо утгууд (ижил бичигдсэнийг оруулахгүй), хамгийн ойрын 3
    function similar(value, items, label) {
        const v = key(value);
        if (v.length < 2) return [];
        return items.map(function (item) {
            const k = key(label(item));
            return { item: item, d: distance(v, k), contains: v.length >= 3 && (k.indexOf(v) !== -1 || v.indexOf(k) !== -1) };
        }).filter(function (r) {
            return label(r.item).toLowerCase() !== String(value).trim().toLowerCase()
                && (r.d <= Math.max(1, Math.floor(key(label(r.item)).length / 4)) || r.contains);
        }).sort(function (a, b) { return a.d - b.d; }).slice(0, 3).map(function (r) { return r.item; });
    }

    function branch() { return String((branchSelect ? branchSelect.value : myBranchId) || ''); }
    function inBranch() { return people.filter(function (u) { return !branch() || String(u.branch || '') === branch(); }); }
    function fullName(u) { return (u.surname ? u.surname + ' ' : '') + u.name; }
    // Сервертэй ижил таарал (Computer::matchHolderUser): нэр яг, овог бичсэн бол овог ч (хоосон овогтой бол таарна)
    function exact() {
        const name = nameInput.value.trim().toLowerCase();
        const surname = surnameInput ? surnameInput.value.trim().toLowerCase() : '';
        return inBranch().filter(function (u) {
            return u.name.toLowerCase() === name && (!surname || !u.surname || u.surname.toLowerCase() === surname);
        });
    }

    function statusBox(input, id) {
        let box = document.getElementById(id);
        if (!box) {
            box = document.createElement('div');
            box.id = id;
            box.className = 'mt-1.5 hidden rounded-lg px-3 py-2 text-xs';
            input.parentElement.appendChild(box);
        }
        return box;
    }
    function show(box, tone, html) {
        box.classList.toggle('hidden', !html);
        box.classList.remove('bg-emerald-50', 'text-emerald-800', 'bg-amber-50', 'text-amber-800');
        if (html) box.classList.add('bg-' + tone + '-50', 'text-' + tone + '-800');
        box.innerHTML = html || '';
    }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function chips(list, label) {
        return list.map(function (item, i) {
            return '<button type="button" data-pick="' + i + '" class="ml-1 mt-1 !min-h-0 !rounded-full !border !border-blue-200 !bg-white !px-2.5 !py-0.5 !text-xs !font-medium !text-blue-700 hover:!bg-blue-50">' + esc(label(item)) + '</button>';
        }).join('');
    }

    let newHolder = false;
    let deptTypo = null;

    function checkHolder() {
        const box = statusBox(nameInput, 'holder-status');
        newHolder = false;
        // Ажилтан аль хэдийн оноогдсон (засах үед) эсвэл нэр хоосон бол сервер шинээр тааруулахгүй
        if ((assigned && assigned.value) || nameInput.disabled || !nameInput.value.trim()) return show(box, '', '');
        const found = exact();
        if (found.length === 1) {
            return show(box, 'emerald', '✓ Ажилтан олдлоо: <b>' + esc(fullName(found[0])) + '</b>' + (found[0].username ? ' (' + esc(found[0].username) + ')' : '') + ' — төхөөрөмж түүнд олгогдоно.');
        }
        if (found.length > 1) {
            return show(box, 'amber', '⚠ Ижил нэртэй ' + found.length + ' ажилтан байна. Автоматаар олгогдохгүй: хадгалсны дараа «Ажилтанд олгох»-оор сонгоно уу.');
        }
        newHolder = true;
        const typed = ((surnameInput ? surnameInput.value.trim() + ' ' : '') + nameInput.value.trim()).trim();
        const near = similar(nameInput.value, inBranch(), function (u) { return u.name; });
        box.dataset.near = JSON.stringify(near);
        show(box, 'amber', '＋ «' + esc(typed) + '» нэртэй ажилтан байхгүй. Хадгалахад <b>шинэ ажилтан үүснэ</b>.'
            + (near.length ? '<div class="mt-1">Та үүнийг хэлсэн үү:' + chips(near, fullName) + '</div>' : ''));
    }

    function checkDept() {
        const deptInput = deptNew && !deptNew.disabled ? deptNew : deptSelect;
        if (!deptInput) return;
        const box = statusBox(deptInput, 'department-status');
        deptTypo = null;
        const value = deptInput.value.trim();
        const known = departments.some(function (d) { return d.toLowerCase() === value.toLowerCase(); });
        if (deptInput.disabled || !value || known) return show(box, '', '');
        const near = similar(value, departments, function (d) { return d; });
        box.dataset.near = JSON.stringify(near);
        if (near.length) deptTypo = { value: value, near: near };
        show(box, 'amber', near.length ? 'Ийм алба жагсаалтад байхгүй. Та үүнийг хэлсэн үү:' + chips(near, function (d) { return d; }) : '');
    }

    // Санал болгосныг дарахад талбарыг бөглөнө (бусад скрипт: бүртгэлийн дугаар ... шинэчлэгдэнэ)
    document.addEventListener('click', function (e) {
        const pick = e.target.closest && e.target.closest('[data-pick]');
        if (!pick) return;
        const box = pick.closest('#holder-status, #department-status');
        const item = JSON.parse(box.dataset.near || '[]')[pick.dataset.pick];
        if (box.id === 'holder-status') {
            if (surnameInput) { surnameInput.value = item.surname || ''; surnameInput.dispatchEvent(new Event('input', { bubbles: true })); }
            nameInput.value = item.name;
            nameInput.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            const deptInput = deptNew && !deptNew.disabled ? deptNew : deptSelect;
            deptInput.value = item;
            deptInput.dispatchEvent(new Event('input', { bubbles: true }));
            deptInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    [nameInput, surnameInput, branchSelect].forEach(function (el) {
        if (el) { el.addEventListener('input', checkHolder); el.addEventListener('change', checkHolder); }
    });
    [deptSelect, deptNew].forEach(function (el) {
        if (el) { el.addEventListener('input', checkDept); el.addEventListener('change', checkDept); }
    });
    checkHolder();
    checkDept();

    // Хадгалахаас өмнө: шинэ ажилтан үүсэх, эсвэл алба хэлтэс үсгийн алдаатай байж болзошгүй бол асууна
    nameInput.form.addEventListener('submit', function (e) {
        checkHolder();
        checkDept();
        const notes = [];
        if (newHolder) {
            notes.push('«' + ((surnameInput ? surnameInput.value.trim() + ' ' : '') + nameInput.value.trim()).trim() + '» нэртэй ажилтан системд байхгүй тул ШИНЭ АЖИЛТАН үүснэ.');
        }
        if (deptTypo) notes.push('«' + deptTypo.value + '» гэсэн шинэ алба нэмэгдэнэ (ойролцоо: «' + deptTypo.near.join('», «') + '»).');
        if (notes.length && !confirm(notes.join('\n\n') + '\n\nҮргэлжлүүлэх үү?')) e.preventDefault();
    });
})();
</script>

<script>
// Диск: SSD, HDD, M.2 талбарыг нуугдсан «storage» талбарт «512GB SSD + 1TB HDD + 256GB M.2» болгон нэгтгэж,
// нийт хэмжээг GB-ээр харуулна (SpecSuggestions::composeStorage, storageTotalGb-тэй ижил; 1TB = 1024GB)
window.storageDisk = (function () {
    const kinds = { ssd: 'SSD', hdd: 'HDD', m2: 'M.2' };
    function compose(values) {
        const out = [];
        Object.keys(kinds).forEach(function (kind) {
            String(values[kind] || '').split(/\s*\+\s*/).forEach(function (part) {
                part = part.trim();
                if (part === '') return;
                if (/^\d+([.,]\d+)?$/.test(part)) part += 'GB';
                out.push(/\b(SSD|HDD|Disk|M\.?2)\b/i.test(part) ? part : part + ' ' + kinds[kind]);
            });
        });
        return out.join(' + ');
    }
    function kindOf(part) { return /\bM\.?2\b/i.test(part) ? 'm2' : (/\bHDD\b/i.test(part) ? 'hdd' : 'ssd'); }
    function split(value) {
        const parts = { ssd: [], hdd: [], m2: [] };
        String(value || '').split(/\s*\+\s*/).forEach(function (part) {
            part = part.trim();
            if (part === '') return;
            const kind = kindOf(part);
            parts[kind].push(part.replace(new RegExp('\\s*\\b' + (kind === 'm2' ? 'M\\.?2' : kind) + '\\b', 'i'), '').trim());
        });
        return { ssd: parts.ssd.join(' + '), hdd: parts.hdd.join(' + '), m2: parts.m2.join(' + ') };
    }
    function totalGb(value) {
        let total = 0, found = false;
        String(value || '').split(/\s*\+\s*/).forEach(function (part) {
            const m = part.match(/(\d+(?:[.,]\d+)?)\s*(GB|TB)/i);
            if (!m) return;
            total += parseFloat(m[1].replace(',', '.')) * (m[2].toUpperCase() === 'TB' ? 1024 : 1);
            found = true;
        });
        return found ? Math.round(total * 10) / 10 : null;
    }
    function part(box, kind) { return box.querySelector('[data-part="' + kind + '"]'); }
    function showTotal(box, value) {
        const out = box.querySelector('[data-storage-total] b');
        if (!out) return;
        const t = totalGb(value);
        out.textContent = t === null ? '—' : t + ' GB';
    }
    function sync(box) {
        const values = {};
        Object.keys(kinds).forEach(function (k) { values[k] = part(box, k).value; });
        box.previousElementSibling.value = compose(values);
        showTotal(box, box.previousElementSibling.value);
    }
    // Нуугдсан утгаас SSD, HDD, M.2 талбарыг бөглөнө (картанд үндсэн хэсгээс хуулах, скриптээр уншсан үед)
    function fill(box) {
        const parts = split(box.previousElementSibling.value);
        Object.keys(kinds).forEach(function (k) { part(box, k).value = parts[k]; });
        showTotal(box, box.previousElementSibling.value);
    }
    document.addEventListener('input', function (e) {
        const box = e.target.closest && e.target.closest('[data-storage-parts]');
        if (box) sync(box);
    });
    // Засах, алдаатай буцахад: бөглөгдсөн талбаруудаас нийтийг шууд харуулна
    document.querySelectorAll('[data-storage-parts]').forEach(sync);
    return { fill: fill, kinds: kinds };
})();

// RAM: нэг талбарын дотор модулиуд chip байдлаар: [4GB] + [4GB ×]  [+ Модуль]   Нийт 8 GB
// Нуугдсан «ram_gb» талбарт «4GB + 4GB» болгон нэгтгэнэ (SpecSuggestions::ram нийлбэрийг бодно)
window.ramModules = (function () {
    // Layout-ын «main input/button» загварыг дарахын тулд ! ашиглана
    const boxClass = 'mt-1 flex min-h-[2.75rem] flex-wrap items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-1.5 py-1.5 shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100';
    // Модуль бүр яг 68px (× нь буланд гадна талд тул өргөнд нөлөөлөхгүй)
    const chipClass = 'relative inline-flex h-8 w-[68px] items-center rounded-lg border border-slate-200 bg-slate-50 transition focus-within:border-blue-300 focus-within:bg-white';
    const inputClass = 'w-full min-w-0 !min-h-0 !rounded-lg !border-0 !bg-transparent !pl-2.5 !pr-1 !py-0 text-left !text-sm font-semibold text-slate-800 !outline-none placeholder:font-normal placeholder:text-slate-400';
    // width/height: CSS ачаалагдаагүй ч икон зөв хэмжээтэй харагдана
    const icon = function (d, px) {
        return '<svg viewBox="0 0 24 24" width="' + px + '" height="' + px + '" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="' + d + '"/></svg>';
    };

    function hidden(box) { return box.previousElementSibling; }
    function inputs(box) { return Array.from(box.querySelectorAll('input[data-ram-module]')); }
    // «8» → «8GB», «8 gb» → «8GB»
    function normalize(part) { return part.replace(/^(\d+)\s*(gb)?$/i, '$1GB'); }

    function sync(box) {
        const parts = inputs(box).map(function (i) { return i.value.trim(); }).filter(Boolean).map(normalize);
        hidden(box).value = parts.join(' + ');
        const valid = parts.length > 1 && parts.every(function (p) { return /^\d+GB$/.test(p); });
        const total = parts.reduce(function (sum, p) { return sum + (parseInt(p, 10) || 0); }, 0);
        const badge = box.querySelector('[data-ram-total]');
        badge.textContent = 'Нийт ' + total + ' GB';
        badge.classList.toggle('hidden', !valid);
    }

    function addModule(box, value) {
        const first = !inputs(box).length;
        const frag = document.createDocumentFragment();
        if (!first) {
            const sep = document.createElement('span');
            sep.dataset.ramSep = '';
            sep.className = 'px-0.5 text-sm font-semibold text-slate-400';
            sep.textContent = '+';
            frag.appendChild(sep);
        }
        const chip = document.createElement('span');
        chip.className = chipClass;
        const input = document.createElement('input');
        input.type = 'text';
        input.dataset.ramModule = '';
        input.autocomplete = 'off';
        input.placeholder = '8GB';
        input.setAttribute('list', 'sg-ram-module');
        input.className = inputClass;
        input.value = value || '';
        chip.appendChild(input);
        // Нэмсэн модулийг хасах (эхнийх үргэлж үлдэнэ): баруун дээд буланд жижиг дугуй ×
        if (!first) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.ramRemove = '';
            remove.title = 'Модулийг хасах';
            remove.className = 'absolute -right-1.5 -top-1.5 z-10 flex h-4 w-4 !min-h-0 items-center justify-center !rounded-full !border !border-slate-200 !bg-white !p-0 !text-slate-400 shadow-sm hover:!border-rose-200 hover:!bg-rose-50 hover:!text-rose-600';
            remove.innerHTML = icon('M6 6l12 12M18 6L6 18', 9);
            chip.appendChild(remove);
        }
        frag.appendChild(chip);
        box.insertBefore(frag, box.querySelector('[data-ram-add]'));
        return input;
    }

    // Нуугдсан утгаас («8GB + 4GB») модулиудыг үүсгэнэ (ачаалахад, картанд үндсэн хэсгээс хуулахад)
    function fill(box) {
        box.classList.add.apply(box.classList, boxClass.split(' '));
        box.innerHTML =
            '<button type="button" data-ram-add title="RAM модуль нэмэх" aria-label="RAM модуль нэмэх" class="ml-0.5 flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border !border-dashed !border-slate-300 !bg-white !p-0 !text-blue-600 hover:!border-blue-400 hover:!bg-blue-50">' +
                icon('M12 5v14M5 12h14', 16) + '</button>' +
            '<span data-ram-total class="ml-auto hidden whitespace-nowrap rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700"></span>';
        const parts = String(hidden(box).value || '').split(/\s*\+\s*/).map(function (p) { return p.trim(); }).filter(Boolean);
        (parts.length ? parts : ['']).forEach(function (p) { addModule(box, p); });
        sync(box);
    }

    document.addEventListener('input', function (e) {
        const box = e.target.closest && e.target.closest('[data-ram-modules]');
        if (box) sync(box);
    });
    document.addEventListener('click', function (e) {
        const box = e.target.closest && e.target.closest('[data-ram-modules]');
        if (!box) return;
        if (e.target.closest('[data-ram-add]')) {
            // Шинэ модуль хоосон: хэмжээг сонгох эсвэл бичнэ
            addModule(box, '').focus();
            sync(box);
        } else if (e.target.closest('[data-ram-remove]')) {
            // Модулийн chip болон өмнөх «+» тэмдэг
            const chip = e.target.closest('[data-ram-remove]').parentElement;
            const sep = chip.previousElementSibling;
            if (sep && 'ramSep' in sep.dataset) sep.remove();
            chip.remove();
            sync(box);
        }
    });

    document.querySelectorAll('[data-ram-modules]').forEach(fill);
    return { fill: fill };
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
    const inventory = document.getElementById('inventory_number');
    const perItem = document.querySelectorAll('[data-per-item]');
    const submit = form.querySelector('button[type="submit"]:not([name])');
    const saveAndNew = document.getElementById('save-and-new');
    const typeSelect = document.getElementById('type');

    // Бүртгэлийн дугаарын урьдчилсан харагдац: PC-B{салбар}-A{админ}-U{хариуцагч}-{огноо}-{өдрийн дугаар}
    const nextSerial = @json($nextSerial ?? 1);
    const inventoryDate = @json($inventoryDate ?? now()->format('Ymd'));
    const myCode = @json(\App\Models\User::registrarCode(auth()->id())); // A2, Superadmin бол SA1
    const myBranchId = @json(auth()->user()->isBranchScoped() ? auth()->user()->branch_id : null);
    const branchSelect = document.getElementById('branch_id');
    const holderSelect = document.getElementById('assigned_user_id');
    const kitSelect = document.getElementById('kit_parent_id');
    const kitHint = document.getElementById('kit-hint');
    // Хариуцагч: овог, нэрийг бичихэд салбарын ганц ажилтантай таарвал түүний код U хэсэгт орно (серверт ч ижил)
    const holderCandidates = @json($holderCandidates ?? []);
    const holderNameInput = document.getElementById('holder_name');
    const holderSurnameInput = document.getElementById('holder_surname');
    // branch === undefined бол бүх салбараас хайна
    function holderMatches(branch) {
        const name = holderNameInput ? holderNameInput.value.trim().toLowerCase() : '';
        const surname = holderSurnameInput ? holderSurnameInput.value.trim().toLowerCase() : '';
        return holderCandidates.filter(function (u) {
            return (branch === undefined || String(u.branch || '') === branch) && u.name.toLowerCase() === name
                && (!surname || !u.surname || u.surname.toLowerCase() === surname);
        });
    }
    function currentBranch() { return String((branchSelect ? branchSelect.value : myBranchId) || ''); }
    function holderCode() {
        if (holderSelect && holderSelect.value && holderSelect.dataset.codeNumber) return holderSelect.dataset.codeNumber;
        if (!holderNameInput || !holderNameInput.value.trim()) return null; // хариуцагч оруулаагүй
        const matches = holderMatches(currentBranch());
        return matches.length === 1 ? matches[0].code : 0;
    }
    // Хариуцагч сонгосон салбарт байхгүй ч өөр салбарын ганц ажилтан бол салбарыг нь сонгоно (Супер админ).
    // Төхөөрөмж хариуцагчийнхаа салбарт бүртгэгдэж, хадгалахад тэр ажилтанд олгогдоно.
    function syncHolderBranch() {
        if (!branchSelect || !holderNameInput || !holderNameInput.value.trim()) return;
        if (holderMatches(currentBranch()).length) return;
        const everywhere = holderMatches(undefined);
        if (everywhere.length === 1 && everywhere[0].branch && branchSelect.querySelector('option[value="' + everywhere[0].branch + '"]')) {
            branchSelect.value = String(everywhere[0].branch);
        }
    }
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
        if (holderCode() === null) {
            // Хариуцагчийг эхэлж оруулсны дараа дугаар автоматаар гарна
            inventory.placeholder = 'Хариуцагчийг оруулсны дараа автоматаар гарна';
            return;
        }
        // Бүртгэсэн хүн + огноо + дугаар: SA1-20261023-001
        inventory.placeholder = myCode + '-' + inventoryDate + '-' + String(nextSerial).padStart(3, '0');
    }
    if (typeSelect) typeSelect.addEventListener('change', previewInventory);
    if (branchSelect) branchSelect.addEventListener('change', previewInventory);
    [holderNameInput, holderSurnameInput].forEach(function (el) {
        // change: браузерын автоматаар бөглөлт (autofill)
        ['input', 'change'].forEach(function (ev) {
            if (el) el.addEventListener(ev, function () { syncHolderBranch(); previewInventory(); });
        });
    });
    syncHolderBranch();
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
        card.querySelectorAll('[data-storage-parts]').forEach(window.storageDisk.fill);
        card.querySelectorAll('[data-ram-modules]').forEach(window.ramModules.fill);
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
            // Диск: SSD, HDD, M.2 тусдаа; нуугдсан items[][storage]-д нэгтгэнэ, ард нь нийт
            if (f === 'storage') {
                const stored = document.createElement('input');
                stored.type = 'hidden';
                stored.name = 'items[' + idx + '][storage]';
                stored.dataset.field = 'storage';
                if (values && values.storage) stored.value = values.storage;
                const box = document.createElement('div');
                box.dataset.storageParts = '';
                box.className = 'contents';
                Object.keys(window.storageDisk.kinds).forEach(function (kind) {
                    const wrap = document.createElement('label');
                    wrap.className = 'block text-xs font-medium text-slate-600';
                    wrap.textContent = 'Санах ой — ' + window.storageDisk.kinds[kind];
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.dataset.part = kind;
                    input.autocomplete = 'off';
                    input.className = inputClass;
                    input.placeholder = { ssd: 'Жишээ: 512GB', hdd: 'Жишээ: 1TB', m2: 'Жишээ: 256GB' }[kind];
                    input.setAttribute('list', 'sg-storage-' + kind);
                    wrap.appendChild(input);
                    box.appendChild(wrap);
                });
                const total = document.createElement('p');
                total.dataset.storageTotal = '';
                total.className = 'self-end pb-2 text-xs text-slate-500';
                total.innerHTML = 'Нийт: <b class="font-semibold text-slate-800">—</b>';
                box.appendChild(total);
                grid.append(stored, box);
                window.storageDisk.fill(box);
                return;
            }
            // RAM: модулиуд «+» товчоор; нуугдсан items[][ram_gb]-д нэгтгэнэ
            if (f === 'ram_gb') {
                const stored = document.createElement('input');
                stored.type = 'hidden';
                stored.name = 'items[' + idx + '][ram_gb]';
                stored.dataset.field = 'ram_gb';
                if (values && values.ram_gb) stored.value = values.ram_gb;
                const box = document.createElement('div');
                box.dataset.ramModules = '';
                const wrap = document.createElement('div');
                wrap.className = 'block text-xs font-medium text-slate-600 sm:col-span-2';
                wrap.textContent = cfg.labels[f];
                wrap.append(stored, box);
                grid.appendChild(wrap);
                window.ramModules.fill(box);
                return;
            }
            const wrap = document.createElement('label');
            wrap.className = 'block text-xs font-medium text-slate-600';
            wrap.textContent = cfg.labels[f];
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'items[' + idx + '][' + f + ']';
            input.dataset.field = f;
            input.autocomplete = 'off';
            input.className = inputClass;
            const list = listFor(cat, f);
            if (list) input.setAttribute('list', list);
            if (f === 'specs') input.placeholder = cfg.specsPlaceholder[cat] || '';
            if (f === 'brand') input.placeholder = 'Сонгох эсвэл бичих';
            if (values && values[f] !== undefined && values[f] !== null) input.value = values[f];
            wrap.appendChild(input);
            grid.appendChild(wrap);
        });

        // Үзүүлэлтгүй (mouse, keyboard): зөвхөн гарчиг, «Хасах»
        if (grid.children.length) card.append(head, hidden, grid);
        else { head.classList.remove('mb-3'); card.append(head, hidden); }
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
            const serial = addCard(card.dataset.category, copy, false, same[same.length - 1]).querySelector('[data-field="serial_number"]');
            if (serial) serial.focus();
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
    const tabs = Array.from(document.querySelectorAll('#category-tabs [data-tab], #accessory-tabs [data-tab]'));
    const accessories = @json(\App\Enums\EquipmentCategory::ACCESSORIES);
    const withBox = document.getElementById('with-accessories');
    const accessoryTabs = document.getElementById('accessory-tabs');
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
        const isAccessory = accessories.indexOf(activeTab) !== -1;
        if (accessoryTabs) {
            accessoryTabs.classList.toggle('hidden', !isAccessory);
            accessoryTabs.classList.toggle('flex', isAccessory);
        }
        tabs.forEach(function (t) {
            const group = t.dataset.tab === 'accessory';
            const on = group ? isAccessory : t.dataset.tab === activeTab;
            const inSet = picked.some(function (b) { return group ? accessories.indexOf(b.value) !== -1 : b.value === t.dataset.tab; });
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
        // Ганц процессор бүртгэхэд mouse, keyboard автоматаар хамт (олон төхөөрөмжид карт бүрээр бүртгэнэ)
        if (withBox) {
            const show = single && activeTab === 'processor';
            withBox.classList.toggle('hidden', !show);
            withBox.querySelectorAll('input').forEach(function (el) { el.disabled = !show; });
        }
        if (nameLabel) nameLabel.textContent = single && cfg.groupOf[activeTab] === 'computer' ? 'Компьютерийн нэр' : 'Төхөөрөмжийн нэр';
        if (nameRequired) nameRequired.classList.toggle('hidden', !isOther);
        if (nameInput) {
            nameInput.required = isOther && !nameInput.disabled;
            nameInput.placeholder = isOther ? 'Ямар төхөөрөмж вэ? Жишээ: Проектор, Веб камер, Чихэвч' : 'Хоосон бол: Үйлдвэрлэгч + Загвар';
        }
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            // «Дагалдах хэрэгсэл»: өмнө сонгосон дэд төрөл, эсвэл Mouse
            activeTab = t.dataset.tab === 'accessory'
                ? (accessories.indexOf(activeTab) !== -1 ? activeTab : accessories[0])
                : t.dataset.tab;
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
