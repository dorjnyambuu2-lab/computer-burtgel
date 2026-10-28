@extends('layouts.app')

@section('title', 'Үйлдлийн түүх')

@section('content')
    @php
        // Шүүлтүүрийн бүх удирдлага ижил өндөртэй (h-10), «Бүх төхөөрөмж» хуудастай ижил загвар
        $input = 'h-10 w-full !min-h-0 !rounded-lg border !border-slate-200 !bg-white !px-3 !py-0 text-sm text-slate-700 shadow-sm focus:!border-blue-400';
        // Түүхийг бүрмөсөн устгах эрх зөвхөн Супер админд
        $isSuper = auth()->user()->isSuperadmin();
        $err = $errors->getBag('deleteLogs');
        $filtered = request()->hasAny(['q', 'action', 'user_id', 'from', 'to']) && collect(request()->only(['q', 'action', 'user_id', 'from', 'to']))->filter()->isNotEmpty();
        $cols = $isSuper ? 8 : 7;
        // Өдрийн гарчиг: «Өнөөдөр», «Өчигдөр», эсвэл огноо
        $dayLabel = fn ($d) => $d->isToday() ? 'Өнөөдөр' : ($d->isYesterday() ? 'Өчигдөр' : $d->format('Y-m-d'));
        // «Dell H2426 [SA1-20261028-005-3]» → нэр, дугаар тусдаа (нэрэнд дугаар орсон бол давтахгүй)
        $subject = function ($label) {
            if (preg_match('/^(.*)\s\[([^\]]+)\]$/u', (string) $label, $m)) {
                return [$m[1], str_contains($m[1], $m[2]) ? null : $m[2]];
            }

            return [$label, null];
        };
        $initials = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name), 0, 2));
        $prevDay = null;
    @endphp

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="!mb-0 text-2xl font-bold text-slate-900">Үйлдлийн түүх</h1>
            <p class="mt-1 text-sm text-slate-500">Төхөөрөмжийн бүх өөрчлөлт: хэн, хэзээ, юуг өөрчилсөн · нийт <b class="font-semibold text-slate-700">{{ $logs->total() }}</b></p>
        </div>
        @if ($isSuper)
            <button type="button" id="delete-selected" disabled
                    class="inline-flex h-10 !min-h-0 items-center gap-1.5 !rounded-lg !border !border-rose-200 !bg-white !px-4 !py-0 !text-sm !font-medium !text-rose-600 transition hover:!bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
                Сонгосныг устгах <span id="selected-count"></span>
            </button>
        @endif
    </div>

    @if ($err->any())
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $err->first() }}</div>
    @endif

    <form method="GET" class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[2fr_1.3fr_1.3fr_1fr_1fr_auto]">
        <label class="block text-xs font-medium text-slate-500">Төхөөрөмж
            <div class="relative mt-1">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="#12, нэр, дугаар..." class="{{ $input }} !pl-9">
            </div>
        </label>
        <label class="block text-xs font-medium text-slate-500">Үйлдэл
            <select name="action" class="{{ $input }} mt-1">
                <option value="">Бүгд</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-500">Хэрэглэгч
            <select name="user_id" class="{{ $input }} mt-1">
                <option value="">Бүгд</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-medium text-slate-500">Эхлэх
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }} mt-1">
        </label>
        <label class="block text-xs font-medium text-slate-500">Дуусах
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }} mt-1">
        </label>
        <div class="flex items-end gap-2">
            <button class="h-10 !min-h-0 !rounded-lg !border-blue-600 !bg-blue-600 !px-5 !py-0 text-sm font-semibold !text-white hover:!bg-blue-700">Шүүх</button>
            @if ($filtered)
                <a href="{{ route('activity.index') }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm !text-slate-500 hover:bg-slate-100 hover:!text-slate-800">Цэвэрлэх</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    @if ($isSuper)
                        <th class="w-10 px-4 py-3"><input type="checkbox" id="select-all" title="Энэ хуудсыг бүгдийг сонгох" class="h-4 w-4"></th>
                    @endif
                    <th class="w-12 px-4 py-3">№</th>
                    <th class="w-16 px-4 py-3">Цаг</th>
                    <th class="px-4 py-3">Хэн</th>
                    <th class="px-4 py-3">Үйлдэл</th>
                    <th class="px-4 py-3">Төхөөрөмж</th>
                    <th class="min-w-72 px-4 py-3">Өөрчлөлт</th>
                    <th class="px-4 py-3"><span class="sr-only">Үйлдэл</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $day = $log->created_at ? $dayLabel($log->created_at) : '—';
                        [$subjectName, $subjectCode] = $subject($log->subject_label);
                    @endphp
                    {{-- Өдөр солигдох бүрт гарчиг мөр --}}
                    @if ($day !== $prevDay)
                        <tr>
                            <td colspan="{{ $cols }}" class="border-b border-slate-100 bg-slate-50/60 px-4 py-2 text-xs font-semibold text-slate-600">
                                {{ $day }}@if ($log->created_at && ($log->created_at->isToday() || $log->created_at->isYesterday()))<span class="ml-1 font-normal text-slate-400">· {{ $log->created_at->format('Y-m-d') }}</span>@endif
                            </td>
                        </tr>
                        @php $prevDay = $day; @endphp
                    @endif
                    {{-- Нэг мөр: дарахад дэлгэрэнгүй цонх (template байвал). Сэргээх товч баруун талд --}}
                    @php $restore = $restoreStates[$log->id] ?? null; @endphp
                    <tr data-log-row class="border-b border-slate-100 transition hover:bg-slate-50">
                        @if ($isSuper)
                            <td class="px-4 py-2.5">
                                @unless ($log->action === 'logs_deleted')
                                    <input type="checkbox" name="ids[]" value="{{ $log->id }}" form="delete-logs-form" class="h-4 w-4" data-log>
                                @endunless
                            </td>
                        @endif
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-400">#{{ $logs->firstItem() + $loop->index }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-500" title="{{ $log->created_at?->format('Y-m-d H:i:s') }}">{{ $log->created_at?->format('H:i') }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[9px] font-bold text-slate-600">{{ $initials($log->user?->name ?? 'С') }}</span>
                                <span class="whitespace-nowrap text-slate-700">{{ $log->user?->name ?? 'Систем' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $log->actionColor() }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $log->actionLabel() }}</span>
                        </td>
                        <td class="max-w-xs px-4 py-2.5">
                            <div class="flex min-w-0 items-baseline gap-2">
                                @if ($restore === 'active')
                                    <a href="{{ route('computers.show', $log->subject_id) }}" title="Төхөөрөмжийг нээх" class="truncate font-semibold !text-slate-900 hover:!text-blue-700 hover:underline">{{ $subjectName }}</a>
                                @else
                                    <span class="truncate font-semibold text-slate-900" title="{{ $subjectName }}">{{ $subjectName }}</span>
                                @endif
                                @if ($subjectCode)<span class="shrink-0 font-mono text-[11px] text-slate-400">{{ $subjectCode }}</span>@endif
                            </div>
                        </td>
                        <td class="max-w-md px-4 py-2.5">@include('activity._changes', ['log' => $log])</td>
                        <td class="w-20 whitespace-nowrap px-4 py-2.5 text-right">
                            {{-- Супер админ: устгасан компьютер, төхөөрөмжийг түүхээс сэргээх --}}
                            @if (in_array($restore, ['trashed', 'snapshot'], true))
                                <form method="POST" action="{{ route('activity.restore', $log) }}" class="inline"
                                      onsubmit="return confirm(@js('«'.$log->subject_label.'»-г сэргээх үү?'))">
                                    @csrf
                                    <button title="Сэргээх" aria-label="Сэргээх"
                                            class="inline-flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-400 transition hover:!bg-emerald-50 hover:!text-emerald-600">
                                        <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.95 8.95 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                                    </button>
                                </form>
                            @endif
                            {{-- Дэлгэрэнгүйтэй мөрөнд сум (JS: template байвал харагдана) --}}
                            <span data-log-chevron class="hidden h-8 w-8 items-center justify-center rounded-lg text-slate-300 transition">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $cols }}" class="px-4 py-14 text-center text-slate-400">
                            {{ $filtered ? 'Шүүлтүүрт тохирох түүх олдсонгүй.' : 'Одоогоор түүх алга.' }}
                            @if ($filtered)<a href="{{ route('activity.index') }}" class="ml-1 !text-blue-600 hover:underline">Шүүлтүүр цэвэрлэх</a>@endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>

    {{-- Өөрчлөлтийн дэлгэрэнгүй (activity._changes-ийн «Дэлгэрэнгүй» товч) --}}
    <dialog id="dlg-log" class="relative rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(48rem,94vw);border:0;">
        <button type="button" onclick="this.closest('dialog').close()" title="Хаах" aria-label="Хаах"
                class="absolute right-4 top-4 z-10 flex h-8 w-8 !min-h-0 items-center justify-center !rounded-lg !border-0 !bg-transparent !p-0 !text-slate-400 hover:!bg-slate-100 hover:!text-slate-700">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <div id="dlg-log-body"></div>
    </dialog>
    <script>
        (function () {
            const dlg = document.getElementById('dlg-log');
            // Дэлгэрэнгүйтэй (template-тэй) мөр: дарж болно, баруун талд сум
            document.querySelectorAll('tr[data-log-row]').forEach(function (row) {
                if (!row.querySelector('template[data-log-detail]')) return;
                row.classList.add('cursor-pointer');
                const chevron = row.querySelector('[data-log-chevron]');
                chevron.classList.replace('hidden', 'inline-flex');
                row.addEventListener('mouseenter', function () { chevron.classList.add('bg-blue-50', 'text-blue-600'); });
                row.addEventListener('mouseleave', function () { chevron.classList.remove('bg-blue-50', 'text-blue-600'); });
                row.addEventListener('click', function (e) {
                    if (e.target.closest('a, button, input, form, label')) return; // чагт, сэргээх, холбоос
                    // Агуулга (толгой + бүлэглэсэн талбарууд) мөр бүрийн template-д бэлэн
                    document.getElementById('dlg-log-body')
                        .replaceChildren(row.querySelector('template[data-log-detail]').content.cloneNode(true));
                    dlg.showModal();
                });
            });
            dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
        })();
    </script>

    @if ($isSuper)
        {{-- Түүх устгахаас өмнө Супер админ нууц үгээ дахин оруулна --}}
        <dialog id="dlg-delete-logs" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/40" style="margin:auto;width:min(28rem,92vw);border:0;">
            <form method="POST" id="delete-logs-form" action="{{ route('activity.destroy') }}" class="space-y-4 p-6">
                @csrf @method('DELETE')
                <h3 class="text-lg font-bold text-rose-700">Түүх бүрмөсөн устгах</h3>
                <p class="text-sm text-slate-600">
                    Сонгосон <b id="dlg-count"></b> түүх бүрмөсөн устгагдаж, <b>сэргээх боломжгүй</b> болно.
                    Хэн, хэдэн түүх устгасан нь түүхэнд үлдэнэ.
                </p>
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
            (function () {
                const boxes = Array.from(document.querySelectorAll('[data-log]'));
                const all = document.getElementById('select-all');
                const button = document.getElementById('delete-selected');
                const dlg = document.getElementById('dlg-delete-logs');

                function update() {
                    const n = boxes.filter(function (b) { return b.checked; }).length;
                    button.disabled = n === 0;
                    document.getElementById('selected-count').textContent = n ? '(' + n + ')' : '';
                    document.getElementById('dlg-count').textContent = n;
                    if (all) all.checked = n > 0 && n === boxes.length;
                }

                boxes.forEach(function (b) { b.addEventListener('change', update); });
                if (all) all.addEventListener('change', function () {
                    boxes.forEach(function (b) { b.checked = all.checked; });
                    update();
                });
                button.addEventListener('click', function () {
                    dlg.showModal();
                    dlg.querySelector('input[type=password]').focus();
                });
                update();
            })();
        </script>
    @endif
@endsection
