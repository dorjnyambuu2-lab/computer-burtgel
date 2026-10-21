{{--
    Ангилал сонгох талбар (процессор, дэлгэц, mouse, keyboard, printer ...).
    Компьютерийн нэмэх/засах формд "Төрөл"-ийн хажууд: @include('computers._category_field')
    Шаардлагатай хувьсагч: $computer
--}}
@php
    $selectedCategory = old('category', $computer->category?->value);
@endphp

<div>
    <label for="category" class="mb-1 block text-sm font-medium text-slate-700">Ангилал</label>

    <select name="category" id="category"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">— Ангилаагүй —</option>
        @foreach (\App\Enums\EquipmentCategory::cases() as $category)
            <option value="{{ $category->value }}" @selected($selectedCategory === $category->value)>
                {{ $category->label() }}
            </option>
        @endforeach
    </select>

    @error('category')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>