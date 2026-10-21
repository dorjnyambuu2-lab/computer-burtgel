@php
    $isEdit = isset($user);
    $input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
@endphp

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-4">
    <div>
        <label class="text-sm font-medium">Нэр</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="{{ $input }}" required>
    </div>

    <div>
        <label class="text-sm font-medium">Нэвтрэх нэр</label>
        <input type="text" name="username" value="{{ old('username', $user->username ?? '') }}" class="{{ $input }}" placeholder="superadmin, admin, employee..." required>
        <p class="mt-1 text-xs text-slate-400">Латин үсэг, тоо, _ болон - ашиглана</p>
    </div>

    <div>
        <label class="text-sm font-medium">Имэйл</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="{{ $input }}" required>
    </div>

    <div>
        <label class="text-sm font-medium">Нууц үг @if ($isEdit)<span class="text-slate-400">(солихгүй бол хоосон үлдээнэ)</span>@endif</label>
        <input type="password" name="password" class="{{ $input }}" {{ $isEdit ? '' : 'required' }}>
        <p class="mt-1 text-xs text-slate-400">Хамгийн багадаа 8 тэмдэгт</p>
    </div>

    <div>
        <label class="text-sm font-medium">Эрх</label>
        <select name="role" class="{{ $input }}">
            <option value="employee" @selected(old('role', $user->role ?? 'employee') === 'employee')>Ажилтан</option>
            <option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>Админ</option>
            <option value="superadmin" @selected(old('role', $user->role ?? '') === 'superadmin')>Superadmin</option>
        </select>
    </div>

    @if ($isEdit)
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
            Идэвхтэй (унтраавал нэвтэрч чадахгүй)
        </label>
    @endif
</div>

<div class="mt-6 flex gap-2">
    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Хадгалах</button>
    <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Болих</a>
</div>