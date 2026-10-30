@extends('layouts.app')

@section('title', 'Устгасан')

@section('content')
    @php
        $isSuper = auth()->user()->isSuperadmin();
        $err = $errors->getBag('forceDelete');
        // Иж бүрдэл: хамт архивлагдсан хэрэгслүүд (mouse, keyboard ...) үндсэн компьютерийнхээ доор ($kitItems).
        // Сэргээх, бүрмөсөн устгах нь үндсэн компьютероор (хэрэгслүүд хамт)
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="!mb-0 text-2xl font-bold text-slate-900">Устгасан</h1>
            <p class="mt-1 text-sm text-slate-500">Архивт {{ $computers->total() }} бүртгэл байна</p>
        </div>
        <a href="{{ route('computers.index') }}"
           class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium !text-slate-600 shadow-sm hover:bg-slate-50">← Бүх төхөөрөмж</a>
    </div>

    @if ($err->any())
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $err->first() }}</div>
    @endif

    <form method="GET" class="mb-4 flex gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Хайх: #12, нэр, дугаар, серийн..."
               class="h-10 min-w-0 flex-1 !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm">
        <button class="h-10 !min-h-0 !rounded-lg !border-slate-800 !bg-slate-800 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-slate-900">Хайх</button>
        @if (filled(request('q')))
            <a href="{{ route('computers.trash') }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm !text-slate-500 hover:bg-slate-100">Цэвэрлэх</a>
        @endif
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-12 px-4 py-3">№</th>
                        <th class="px-4 py-3">Төхөөрөмж</th>
                        <th class="w-56 px-4 py-3">Устгасан</th>
                        <th class="w-52 px-4 py-3"><span class="sr-only">Үйлдэл</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($computers as $computer)
                        @php $kit = $kitItems[$computer->id] ?? collect(); @endphp
                        <tr class="align-top">
                            <td class="px-4 py-4 font-mono text-xs text-slate-400">#{{ $computers->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($computer->category)
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $computer->category->icon() }}"/></svg>
                                    @endif
                                    <span class="font-semibold text-slate-900">{{ $computer->name }}</span>
                                    @if ($kit->isNotEmpty())
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">+{{ $kit->count() }} иж бүрдэл</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 text-xs text-slate-500">
                                    <span class="font-mono">{{ $computer->inventory_number }}</span>
                                    @if ($computer->category) · {{ $computer->category->label() }}@endif
                                    @if ($computer->serial_number) · S/N {{ $computer->serial_number }}@endif
                                </div>
                                {{-- Хамт архивлагдсан иж бүрдлийн хэрэгслүүд --}}
                                @if ($kit->isNotEmpty())
                                    <ul class="mt-2 space-y-1 border-l-2 border-slate-100 pl-3">
                                        @foreach ($kit as $item)
                                            <li class="text-xs text-slate-500"><span class="font-medium text-slate-700">{{ $item->category?->label() ?? $item->name }}</span> · <span class="font-mono">{{ $item->inventory_number }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-slate-700">{{ $computer->deleter?->name ?? '—' }}</div>
                                <div class="mt-0.5 text-xs text-slate-400">{{ $computer->deleted_at?->format($computer->deleted_at->isCurrentYear() ? 'm-d H:i' : 'Y-m-d H:i') }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('computers.restore', $computer->id) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex h-9 items-center gap-1.5 !min-h-0 whitespace-nowrap !rounded-lg !border !border-emerald-200 !bg-emerald-50 !px-3 !py-0 text-sm font-medium !text-emerald-700 hover:!bg-emerald-100">
                                            <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.95 8.95 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                                            Сэргээх
                                        </button>
                                    </form>
                                    @if ($isSuper)
                                        <button type="button" title="Бүрмөсөн устгах" aria-label="Бүрмөсөн устгах"
                                                class="inline-flex h-9 w-9 !min-h-0 items-center justify-center !rounded-lg !border !border-slate-200 !bg-white !p-0 !text-slate-500 hover:!border-rose-200 hover:!bg-rose-50 hover:!text-rose-600"
                                                data-force-delete="{{ route('computers.force-delete', $computer->id) }}"
                                                data-label="{{ $computer->activityLabel() }}{{ $kit->isNotEmpty() ? ' (иж бүрдлийн '.$kit->count().' хэрэгсэлтэй)' : '' }}">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">{{ filled(request('q')) ? 'Хайлтад тохирох төхөөрөмж алга.' : 'Устгасан төхөөрөмж алга.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($computers->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $computers->links() }}</div>
        @endif
    </section>

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
