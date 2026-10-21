@props([
    'name', 'label', 'type' => 'text', 'value' => null, 'options' => null,
    'required' => false, 'list' => null, 'placeholder' => null, 'wide' => false,
])
@php
    $val = $type === 'password' ? null : old($name, $value);
    $cls = 'mt-1 block w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 '
        .($errors->has($name) ? 'border-red-400' : 'border-slate-300');
@endphp
<div class="{{ $wide ? 'md:col-span-2' : '' }}">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
    </label>

    @if ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" class="{{ $cls }}" @required($required)>
            @unless ($required)<option value="">— Сонгох —</option>@endunless
            @foreach ($options as $k => $l)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $l }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="{{ $cls }}" placeholder="{{ $placeholder }}">{{ $val }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}"
               @if ($list) list="{{ $list }}" @endif placeholder="{{ $placeholder }}"
               @if ($type === 'number') min="1" @endif
               class="{{ $cls }}" @required($required)>
    @endif

    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
