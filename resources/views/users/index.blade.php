@extends('layouts.app')
@section('title', 'Хэрэглэгч')

@php
    use Illuminate\Support\Facades\Schema;

    $me = auth()->user();
    $q = trim((string) request('q'));
    $role = request('role');
    $status = request('status');
    $hasLast = Schema::hasColumn('users', 'last_seen_at');

    $query = \App\Models\User::query();
    // Админ: зөвхөн өөрийн салбарын ажилтнууд (Супер админ, бусад админ, бусад салбар харагдахгүй)
    $isBranchAdmin = $me->isBranchScoped();
    if ($isBranchAdmin) {
        $query->where('role', 'employee')->where('branch_id', $me->branch_id);
    }
    $roleOptions = $me->isSuperadmin() ? \App\Models\User::ROLES : array_diff_key(\App\Models\User::ROLES, ['superadmin' => 1]);
    // «#12» бол дэс дугаараар хайна (доор), бусад үед нэр, нэвтрэх нэр, имэйлээр
    $rowNumber = \App\Support\RowNumber::parse($q);
    if ($q !== '' && $rowNumber === null) {
        $query->where(fn($w) => $w->where('name', 'like', "%$q%")->orWhere('surname', 'like', "%$q%")->orWhere('department', 'like', "%$q%")->orWhere('position', 'like', "%$q%")->orWhere('username', 'like', "%$q%")->orWhere('email', 'like', "%$q%"));
    }
    if ($role && array_key_exists($role, \App\Models\User::ROLES)) {
        $query->where('role', $role);
    }
    if ($status === 'active') $query->where('is_active', true);
    if ($status === 'inactive') $query->where('is_active', false);

    // Салбараар шүүх
    $branchOptions = \App\Models\Branch::query()->orderBy('name')->pluck('name', 'id');
    $branchFilter = (string) request('branch');
    if ($branchFilter !== '' && $branchOptions->has((int) $branchFilter)) {
        $query->where('branch_id', (int) $branchFilter);
    }
    $query->with('branch:id,name');

    // Бүртгэсэн огноогоор шүүх
    foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request($key))) {
            $query->whereDate('created_at', $op, request($key));
        }
    }

    // Эрэмбэлэх: ?sort=name|username|role|email|last_seen_at|created_at&dir=asc|desc
    $sorts = ['name', 'username', 'role', 'email', 'created_at'] + ($hasLast ? [5 => 'last_seen_at'] : []);
    $sort = in_array(request('sort'), $sorts, true) ? request('sort') : 'name';
    $dir = request('dir') === 'desc' ? 'desc' : 'asc';
    $sortLink = function (string $key, string $label) use ($sort, $dir) {
        $next = $sort === $key && $dir === 'asc' ? 'desc' : 'asc';
        $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';

        return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null])).'" class="!text-slate-500 hover:!text-slate-900">'.e($label).$arrow.'</a>';
    };

    $users = \App\Support\RowNumber::paginate($query->orderBy($sort, $dir)->orderBy('id'), request(), 15, $rowNumber);
    $filtered = $q !== '' || $role || $status || $branchFilter !== '' || request('date_from') || request('date_to');
    $inp = 'rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
@endphp

@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        @if ($isBranchAdmin)
            <h1 class="!mb-0 text-xl font-bold">Ажилтан <span class="text-base font-normal text-slate-400">· {{ $me->branch?->name }}</span></h1>        @else
            <h1 class="!mb-0 text-xl font-bold">Хэрэглэгч</h1>
        @endif
    </div>
    <a href="{{ url('/users/create') }}" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold !text-white shadow-sm hover:bg-blue-700">
        <span class="text-lg leading-none">+</span> {{ $isBranchAdmin ? 'Ажилтан нэмэх' : 'Админ нэмэх' }}
    </a>
</div>

<form method="GET" action="{{ url('/users') }}" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <input type="search" name="q" value="{{ $q }}" placeholder="Хайх: #12, нэр, нэвтрэх нэр, имэйл" class="{{ $inp }} min-w-60 flex-1">
    @unless ($isBranchAdmin)
        <select name="role" class="{{ $inp }}">
            <option value="">Бүх эрх</option>
            @foreach ($roleOptions as $k => $v)
                <option value="{{ $k }}" @selected($role === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="branch" class="{{ $inp }}">
            <option value="">Бүх салбар</option>
            @foreach ($branchOptions as $id => $name)
                <option value="{{ $id }}" @selected($branchFilter === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
    @endunless
    <select name="status" class="{{ $inp }}">
        <option value="">Бүх төлөв</option>
        <option value="active" @selected($status === 'active')>Идэвхтэй</option>
        <option value="inactive" @selected($status === 'inactive')>Идэвхгүй</option>
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" title="Бүртгэсэн: -аас" class="{{ $inp }}">
    <input type="date" name="date_to" value="{{ request('date_to') }}" title="Бүртгэсэн: хүртэл" class="{{ $inp }}">
    <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
    <button type="submit" class="rounded-xl border border-blue-600 bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Хайх</button>
    @if ($filtered)
        <a href="{{ url('/users') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">Шүүлтүүр цэвэрлэх</a>
    @endif
</form>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="w-12">№</th>
                    <th>ID</th>
                    <th>{!! $sortLink('name', 'Нэр') !!}</th><th>{!! $sortLink('username', 'Нэвтрэх нэр') !!}</th>
                    <th>{!! $sortLink('role', 'Эрх') !!}</th><th>Салбар</th><th>{!! $sortLink('email', 'Имэйл') !!}</th>
                    <th>{!! $hasLast ? $sortLink('last_seen_at', 'Сүүлд идэвхтэй байсан') : 'Сүүлд идэвхтэй байсан' !!}</th>
                    <th>{!! $sortLink('created_at', 'Бүртгэсэн') !!}</th><th>Төлөв</th><th class="text-right">Үйлдэл</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    @php
                        $last = $hasLast ? $u->last_seen_at : null;
                    @endphp
                    <tr>
                        <td class="font-mono text-xs font-semibold text-slate-500">#{{ $users->firstItem() + $loop->index }}</td>
                        <td><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-slate-600">{{ $u->idCode() }}</span></td>
                        <td class="font-semibold text-slate-900">
                            <span class="whitespace-nowrap">{{ trim($u->surname.' '.$u->name) }}</span>
                            @if ($u->department || $u->position)
                                <span class="mt-0.5 block whitespace-nowrap text-xs font-normal text-slate-500">{{ collect([$u->department, $u->position])->filter()->implode(' · ') }}</span>
                            @endif
                        </td>
                        <td class="text-slate-600">{{ $u->username ?: '—' }}</td>
                        <td><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $u->roleBadgeClass() }}">{{ $u->roleLabel() }}</span></td>
                        <td class="text-slate-600">
                            @if ($u->isSuperadmin())
                                <span class="text-xs text-slate-400">Бүх салбар</span>
                            @elseif ($u->branch)
                                {{ $u->branch->name }} <span class="font-mono text-xs text-slate-400">{{ $u->branch->idCode() }}</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">⚠ Оноогоогүй</span>
                            @endif
                        </td>
                        <td class="text-slate-600">{{ $u->email }}</td>
                        <td class="text-slate-600" @if ($last) title="{{ $last->format('Y-m-d H:i') }}" @endif>
                            {{ $last ? $last->locale('mn')->diffForHumans() : '—' }}
                        </td>
                        <td class="whitespace-nowrap text-slate-600">{{ $u->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if ($u->is_active)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Идэвхтэй</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">Идэвхгүй</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                @if ($me->canEditUser($u))
                                    <a href="{{ url('/users/' . $u->id . '/edit') }}"
                                       class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold !text-slate-700 hover:bg-slate-100">Засах</a>
                                @else
                                    <span class="text-xs text-slate-400">Эрхгүй</span>
                                @endif
                                @if ($me->canDeleteUser($u))
                                    <form method="POST" action="{{ url('/users/' . $u->id) }}"
                                          onsubmit="return confirm('«{{ $u->name }}» хэрэглэгчийг устгах уу?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Устгах</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-10 text-center text-slate-400">Хэрэглэгч олдсонгүй.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
        <span>Нийт {{ $users->total() }}</span>
        {{ $users->links() }}
    </div>
</section>
@endsection