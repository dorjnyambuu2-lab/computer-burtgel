@extends('layouts.app')

@section('title', 'Устгасан')

@section('content')
    @php $isSuper = auth()->user()->isSuperadmin(); $err = $errors->getBag('forceDelete'); @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="!mb-0 text-xl font-bold">Устгасан</h1>        </div>
        <a href="{{ route('computers.index') }}" class="text-sm text-indigo-600 hover:underline">← Компьютерүүд</a>
    </div>

    @if ($err->any())
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $err->first() }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Архиваас хайх: #12, нэр, дугаар, серийн..." class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white">Хайх</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">№</th>
                    <th class="px-4 py-3 font-medium">Компьютер</th>
                    <th class="px-4 py-3 font-medium">Устгасан</th>
                    <th class="px-4 py-3 font-medium">Устгасан огноо</th>
                    <th class="px-4 py-3 text-right font-medium">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($computers as $computer)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-500">#{{ $computers->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3 font-medium">
                            {{ $computer->activityLabel() }}
                            <span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-normal text-rose-700">Устгагдсан</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $computer->deleter?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $computer->deleted_at?->format('Y-m-d H:i') }}</td>
                        {{-- Сэргээх, бүрмөсөн устгах: икон товч, hover-т өнгө, тайлбар (title) --}}
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <form method="POST" action="{{ route('computers.restore', $computer->id) }}" class="inline">
                                @csrf
                                <button type="submit" title="Сэргээх" aria-label="Сэргээх"
                                        class="inline-flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-500 transition hover:!bg-emerald-50 hover:!text-emerald-600">
                                    {{-- Түүх (цагтай буцах сум) --}}
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.95 8.95 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                                </button>
                            </form>

                            @if ($isSuper)
                                <button type="button" title="Бүрмөсөн устгах" aria-label="Бүрмөсөн устгах"
                                        class="ml-1 inline-flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-500 transition hover:!bg-rose-50 hover:!text-rose-600"
                                        data-force-delete="{{ route('computers.force-delete', $computer->id) }}"
                                        data-label="{{ $computer->activityLabel() }}">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Устгасан компьютер байхгүй.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $computers->links() }}</div>

    @if ($isSuper)
        {{-- Бүрмөсөн устгахаас өмнө Супер админ нууц үгээ дахин оруулна --}}
        <dialog id="dlg-force" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(28rem,92vw);border:0;">
            <form method="POST" id="force-form" action="" class="space-y-4 p-6">
                @csrf @method('DELETE')
                <h3 class="text-lg font-bold text-rose-700">Бүрмөсөн устгах</h3>
                <p class="text-sm text-slate-600"><b id="force-label"></b> бааз болон архиваас бүрмөсөн устгагдаж, <b>сэргээх боломжгүй</b> болно.</p>
                <label class="block text-sm font-medium text-slate-700">Баталгаажуулахын тулд нууц үгээ оруулна уу
                    <input type="password" name="password" required autocomplete="current-password" class="mt-1 block w-full">
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="this.closest('dialog').close()">Цуцлах</button>
                    <button type="submit" class="btn-danger !border-rose-600 !bg-rose-600 !text-white">Бүрмөсөн устгах</button>
                </div>
            </form>
        </dialog>
        <script>
            document.querySelectorAll('[data-force-delete]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var dlg = document.getElementById('dlg-force');
                    document.getElementById('force-form').action = b.dataset.forceDelete;
                    document.getElementById('force-label').textContent = b.dataset.label;
                    dlg.showModal();
                    dlg.querySelector('form input:not([type=hidden])').focus();
                });
            });
        </script>
    @endif
@endsection
