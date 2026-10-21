@extends('layouts.app')

@section('title', 'Үйлдлийн түүх')

@section('content')
    @php
        $input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
        // Түүхийг бүрмөсөн устгах эрх зөвхөн Супер админд
        $isSuper = auth()->user()->isSuperadmin();
        $err = $errors->getBag('deleteLogs');
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="!mb-0 text-xl font-bold">Үйлдлийн түүх</h1>
        @if ($isSuper)
            <button type="button" id="delete-selected" disabled
                    class="!border-rose-200 !bg-white !text-rose-600 hover:!bg-rose-50 disabled:opacity-40">
                Сонгосныг устгах <span id="selected-count"></span>
            </button>
        @endif
    </div>

    @if ($err->any())
        <div class="mb-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $err->first() }}</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <div>
            <label class="block text-xs text-slate-500">Компьютер</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="#12, нэр, серийн..." class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Үйлдэл</label>
            <select name="action" class="{{ $input }}">
                <option value="">Бүгд</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Хэрэглэгч</label>
            <select name="user_id" class="{{ $input }}">
                <option value="">Бүгд</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500">Эхлэх</label>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-xs text-slate-500">Дуусах</label>
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}">
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Шүүх</button>
        <a href="{{ route('activity.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Цэвэрлэх</a>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    @if ($isSuper)
                        <th class="w-10 px-4 py-3"><input type="checkbox" id="select-all" title="Энэ хуудсыг бүгдийг сонгох" class="h-4 w-4"></th>
                    @endif
                    <th class="w-12 px-4 py-3 font-medium">№</th>
                    <th class="px-4 py-3 font-medium">Огноо</th>
                    <th class="px-4 py-3 font-medium">Хэн</th>
                    <th class="px-4 py-3 font-medium">Үйлдэл</th>
                    <th class="px-4 py-3 font-medium">Компьютер</th>
                    <th class="px-4 py-3 font-medium">Өөрчлөлт</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr class="align-top">
                        @if ($isSuper)
                            <td class="px-4 py-3">
                                @unless ($log->action === 'logs_deleted')
                                    <input type="checkbox" name="ids[]" value="{{ $log->id }}" form="delete-logs-form" class="h-4 w-4" data-log>
                                @endunless
                            </td>
                        @endif
                        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-500">#{{ $logs->firstItem() + $loop->index }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'Систем' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $log->actionColor() }}">{{ $log->actionLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium">
                            {{ $log->subject_label }}
                            {{-- Супер админ: устгасан компьютер, төхөөрөмжийг түүхээс сэргээх --}}
                            @switch($restoreStates[$log->id] ?? null)
                                @case('trashed')
                                @case('snapshot')
                                    <form method="POST" action="{{ route('activity.restore', $log) }}" class="mt-1"
                                          onsubmit="return confirm('«{{ addslashes($log->subject_label) }}»-г сэргээх үү?')">
                                        @csrf
                                        <button class="!rounded-lg !border-emerald-300 !bg-emerald-50 !px-2.5 !py-1 !text-xs !font-semibold !text-emerald-700 hover:!bg-emerald-100">
                                            ↺ Сэргээх
                                        </button>
                                    </form>
                                    @break
                                @case('active')
                                    <a href="{{ route('computers.show', $log->subject_id) }}" class="mt-1 block text-xs font-normal !text-emerald-700 hover:underline">✓ Идэвхтэй байна · нээх →</a>
                                    @break
                                @case('lost')
                                    <div class="mt-1 text-xs font-normal text-slate-400">Сэргээх мэдээлэл алга</div>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3">@include('activity._changes', ['log' => $log])</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isSuper ? 7 : 6 }}" class="px-4 py-8 text-center text-slate-400">Түүх олдсонгүй.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>

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
