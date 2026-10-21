@extends('layouts.app')
@section('title', 'Нууц үг сэргээх')
@section('content')
@php
    $badge = ['pending' => 'bg-amber-50 text-amber-700', 'resolved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-slate-100 text-slate-600'];
@endphp
<div class="mb-4">
    <h1 class="!mb-0 text-xl font-bold">Нууц үг сэргээх</h1>
</div>

@if ($temp = session('temporary_password'))
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <div class="font-semibold">{{ $temp['user'] }} — түр нууц үг:</div>
        <div class="mt-2 select-all font-mono text-lg tracking-wider">{{ $temp['password'] }}</div>
    </div>
@endif

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
        <option value="">Бүх төлөв</option>
        @foreach (\App\Models\PasswordResetRequest::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
    </select>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead><tr><th>Хэрэглэгч</th><th>Эрх</th><th>Илгээсэн</th><th>IP</th><th>Төлөв</th><th class="text-right">Үйлдэл</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td><div class="font-semibold">{{ $r->user->name }}</div><div class="text-xs text-slate-500">{{ $r->user->username }} · {{ $r->user->email }}</div></td>
                <td><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $r->user->roleBadgeClass() }}">{{ $r->user->roleLabel() }}</span></td>
                <td class="whitespace-nowrap text-slate-600">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $r->ip_address ?? '—' }}</td>
                <td>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$r->status] ?? '' }}">{{ $r->statusLabel() }}</span>
                    @if ($r->resolver)<div class="mt-1 text-xs text-slate-400">{{ $r->resolver->name }} · {{ $r->resolved_at?->format('Y-m-d H:i') }}</div>@endif
                </td>
                <td class="whitespace-nowrap text-right">
                    @if ($r->status === 'pending')
                        <form method="POST" action="{{ route('password-requests.resolve', $r) }}" class="inline">@csrf
                            <button type="submit" class="!py-1.5 text-xs">Түр нууц үг олгох</button>
                        </form>
                        <form method="POST" action="{{ route('password-requests.reject', $r) }}" class="inline">@csrf
                            <button class="btn-danger !py-1.5 text-xs text-rose-600">Татгалзах</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-10 text-center text-slate-400">Хүсэлт алга.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $requests->links() }}</div>
@endsection
