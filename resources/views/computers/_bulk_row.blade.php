{{--
    Багцаар бүртгэх хуудасны нэг мөр (нэг төхөөрөмж).
    Хувьсагч: $i (мөрийн индекс, эсвэл загварт __INDEX__), $row (утгууд; _auto = скриптээр уншсан)
    Талбарын дараалал: үйлдвэрлэгч, загвар, серийн дугаар, бүртгэлийн дугаар, дараа нь ангиллаас хамаарах талбарууд
    (процессор: CPU, RAM ...; дэлгэц: үзүүлэлт ...). Хамааралгүйг нь JS нууна.
--}}
@php
    $f = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $lbl = 'mb-0.5 block text-[11px] font-medium text-slate-500';
    $placeholders = ['brand' => 'Сонгох эсвэл бичих', 'mac_address' => 'AA:BB:CC:DD:EE:FF', 'ip_address' => '192.168.1.10'];
    $auto = ! empty($row['_auto']);
    $input = fn (string $field) => 'rows['.$i.']['.$field.']';
@endphp
<div class="rounded-xl border transition {{ $auto ? 'border-emerald-300' : 'border-slate-200' }}" data-row>
    {{-- Толгой: дугаар, ангилал, нэр, үйлдэл --}}
    <div class="flex flex-wrap items-center gap-2 rounded-t-xl border-b px-3 py-2 {{ $auto ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100 bg-slate-50' }}">
        <span data-number class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-700 text-xs font-bold text-white"></span>
        <select name="{{ $input('category') }}" data-category aria-label="Ангилал"
                class="w-44 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-semibold focus:border-blue-500 focus:outline-none">
            @foreach (\App\Enums\EquipmentCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected((string) ($row['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </select>
        <input type="text" name="{{ $input('name') }}" value="{{ $row['name'] ?? '' }}" aria-label="Нэр"
               placeholder="Нэр (хоосон бол автоматаар)"
               class="min-w-40 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none">
        @if ($auto)<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">авто</span>@endif
        <span data-empty class="hidden text-xs text-slate-400">Хоосон · бүртгэгдэхгүй</span>
        <span class="ml-auto flex items-center gap-3">
            <button type="button" data-dup title="Ижил төхөөрөмж дахин нэмэх (жишээ нь 2 дэлгэц)"
                    class="!border-0 !bg-transparent !p-0 !text-xs !font-medium text-blue-600 hover:underline">+ Дахин</button>
            <button type="button" data-remove title="Хасах"
                    class="!border-0 !bg-transparent !p-0 !text-base !font-normal leading-none text-slate-400 hover:text-rose-600">✕</button>
        </span>
    </div>

    {{-- Үзүүлэлт --}}
    <div class="grid gap-x-3 gap-y-2 p-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['brand', 'model', 'serial_number'] as $field)
            <div data-field="{{ $field }}">
                <label class="{{ $lbl }}">{{ \App\Support\SpecSuggestions::LABELS[$field] }}</label>
                <input type="text" name="{{ $input($field) }}" value="{{ $row[$field] ?? '' }}" placeholder="{{ $placeholders[$field] ?? '' }}" autocomplete="off" class="{{ $f }}">
            </div>
        @endforeach
        <div>
            <label class="{{ $lbl }}">Бүртгэлийн дугаар</label>
            <input type="text" value="" placeholder="Автоматаар (PC-{{ now()->format('Ymd') }}-001 ...)" readonly tabindex="-1" class="{{ $f }} cursor-not-allowed bg-slate-50">
        </div>
        <div>
            <label class="{{ $lbl }}">Үнэ (₮)</label>
            <input type="text" name="{{ $input('purchase_price') }}" value="{{ $row['purchase_price'] ?? '' }}" inputmode="decimal" placeholder="Жишээ: 450,000" class="{{ $f }}">
        </div>
        @foreach (['cpu', 'ram_gb', 'storage', 'os', 'ip_address', 'mac_address', 'specs'] as $field)
            <div data-field="{{ $field }}">
                <label class="{{ $lbl }}">{{ \App\Support\SpecSuggestions::LABELS[$field] }}</label>
                <input type="{{ $field === 'ram_gb' ? 'number' : 'text' }}" name="{{ $input($field) }}" value="{{ $row[$field] ?? '' }}"
                       placeholder="{{ $placeholders[$field] ?? '' }}" autocomplete="off" @if ($field === 'ram_gb') min="1" step="1" @endif
                       class="{{ $f }}">
            </div>
        @endforeach
    </div>
</div>
