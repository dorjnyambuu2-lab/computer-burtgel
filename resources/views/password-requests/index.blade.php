@extends('layouts.app')
@section('title', 'Нууц үг сэргээх')
@section('content')
@php
    $badge = ['pending' => 'bg-amber-100 text-amber-800', 'resolved' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-slate-100 text-slate-600'];
    $current = (string) request('status');
    // Төлвийн табууд: Бүгд + төлөв бүр (тоотой)
    $tabs = ['' => ['Бүгд', array_sum($statusCounts)]];
    foreach (\App\Models\PasswordResetRequest::STATUSES as $k => $v) {
        $tabs[$k] = [$v, (int) ($statusCounts[$k] ?? 0)];
    }
@endphp

<div class="mb-5">
    <h1 class="!mb-0 text-2xl font-bold text-slate-900">Нууц үг сэргээх</h1>
    @if ($statusCounts['pending'] ?? 0)
        <p class="mt-1 text-sm text-amber-700"><b>{{ $statusCounts['pending'] }}</b> хүсэлт шийдвэрлэхийг хүлээж байна</p>
    @endif
</div>

{{-- Олгосон түр нууц үг: хуулж аваад хэрэглэгчид дамжуулна --}}
@if ($temp = session('temporary_password'))
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4">
        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-amber-900">{{ $temp['user'] }} — түр нууц үг</div>
            <div class="mt-1 select-all font-mono text-xl tracking-wider text-slate-900" data-temp-password>{{ $temp['password'] }}</div>
            <div class="mt-1 text-xs text-amber-800">Хэрэглэгч энэ нууц үгээр нэвтэрмэгц шинэ нууц үг солино.</div>
        </div>
        <button type="button" data-copy-temp
                class="h-9 !min-h-0 !rounded-lg !border !border-amber-300 !bg-white !px-4 !py-0 text-sm font-semibold !text-amber-900 hover:!bg-amber-100">Хуулах</button>
    </div>
@endif

{{-- Төлвийн табууд --}}
<div class="mb-4 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
    @foreach ($tabs as $value => [$label, $count])
        @php $on = $current === (string) $value; @endphp
        <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
           class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $on ? 'bg-slate-900 !text-white' : '!text-slate-600 hover:bg-slate-100' }}">
            {{ $label }}
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $on ? 'bg-white/20 text-white' : ($value === 'pending' && $count ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-500') }}">{{ $count }}</span>
        </a>
    @endforeach
</div>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Хэрэглэгч</th>
                    <th class="w-44 px-5 py-3">Илгээсэн</th>
                    <th class="w-52 px-5 py-3">Төлөв</th>
                    <th class="w-80 px-5 py-3"><span class="sr-only">Үйлдэл</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($requests as $r)
                @php $pending = $r->status === 'pending'; @endphp
                <tr class="align-middle {{ $pending ? 'bg-amber-50/40' : '' }}">
                    {{-- Хэрэглэгч: нэр, эрх, нэвтрэх нэр --}}
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $r->user->name }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $r->user->roleBadgeClass() }}">{{ $r->user->roleLabel() }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ $r->user->username }}@if ($r->user->email) · {{ $r->user->email }}@endif</div>
                    </td>
                    {{-- Огноо, IP --}}
                    <td class="px-5 py-4">
                        <div class="text-slate-700">{{ $r->created_at->format($r->created_at->isCurrentYear() ? 'm-d H:i' : 'Y-m-d') }}</div>
                        @if ($r->ip_address)<div class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $r->ip_address }}</div>@endif
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[$r->status] ?? 'bg-slate-100 text-slate-600' }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $r->statusLabel() }}</span>
                        @if ($r->resolver)<div class="mt-1 text-[11px] text-slate-400">{{ $r->resolver->name }} · {{ $r->resolved_at?->format('m-d H:i') }}</div>@endif
                    </td>
                    <td class="px-5 py-4">
                        @if ($pending)
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('password-requests.reject', $r) }}"
                                      onsubmit="return confirm(@js($r->user->name.'-ийн хүсэлтийг татгалзах уу?'))">@csrf
                                    <button class="h-9 !min-h-0 !rounded-lg !border !border-slate-200 !bg-white !px-3 !py-0 text-sm font-medium !text-slate-600 hover:!border-rose-200 hover:!bg-rose-50 hover:!text-rose-700">Татгалзах</button>
                                </form>
                                <form method="POST" action="{{ route('password-requests.resolve', $r) }}">@csrf
                                    <button class="h-9 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-0 text-sm font-semibold whitespace-nowrap !text-white hover:!bg-blue-700">Түр нууц үг олгох</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-5 py-14 text-center">
                        <div class="text-sm font-medium text-slate-500">{{ $current === 'pending' ? 'Шийдвэрлэх хүсэлт алга.' : 'Хүсэлт алга.' }}</div>                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $requests->links() }}</div>
    @endif
</section>

<script>
// Түр нууц үгийг хуулах
document.querySelector('[data-copy-temp]')?.addEventListener('click', function () {
    const btn = this;
    navigator.clipboard.writeText(document.querySelector('[data-temp-password]').textContent.trim()).then(function () {
        btn.textContent = 'Хуулсан ✓';
        setTimeout(function () { btn.textContent = 'Хуулах'; }, 2000);
    });
});
</script>
@endsection
