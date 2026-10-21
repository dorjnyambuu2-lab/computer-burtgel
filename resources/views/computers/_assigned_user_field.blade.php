{{--
    Хариуцагч сонгох талбар.
    Компьютерийн нэмэх/засах формд хуучин "Хариуцагч" input-ийн оронд:
        @include('computers._assigned_user_field')
    Шаардлагатай хувьсагч: $computer, $employees (ComputerController дамжуулна)
--}}
@php
    $selectedUserId = old('assigned_user_id', $computer->assigned_user_id);
@endphp

<div>
    <label for="assigned_user_id" class="mb-1 block text-sm font-medium text-slate-700">Хариуцагч</label>

    <select name="assigned_user_id" id="assigned_user_id"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">— Оноогоогүй —</option>
        @foreach ($employees as $employee)
            <option value="{{ $employee->id }}" @selected((string) $selectedUserId === (string) $employee->id)>
                {{ $employee->name }} ({{ $employee->username ?: $employee->email }}) · {{ $employee->roleLabel() }}
            </option>
        @endforeach
    </select>

    @error('assigned_user_id')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror

    {{-- Хуучин текстээр бичигдсэн, системийн хэрэглэгчтэй холбогдоогүй хариуцагч --}}
    @if (! $computer->assigned_user_id && filled($computer->assigned_to))
        <p class="mt-1 text-xs text-amber-600">
            Бичигдсэн хариуцагч: «{{ $computer->assigned_to }}» — системийн хэрэглэгчтэй холбогдоогүй байна.
            Жагсаалтаас сонгоно уу.
        </p>
    @endif
</div>