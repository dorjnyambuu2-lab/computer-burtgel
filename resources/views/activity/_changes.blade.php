{{--
    $log: App\Models\ActivityLog. Хүснэгтэд нэг мөр хураангуй («Төлөв: Засварт → Ашиглагдаж байгаа» +3);
    мөр дээр дарахад бүх талбар бүлэглэгдэж цонхонд (activity/index-ийн #dlg-log) харагдана.
--}}
@php
    $changes = $log->changes ?? [];
    // Бүрмөсөн устгахаас өмнөх бүтэн хуулбарыг жагсаахгүй (сэргээхэд л ашиглана)
    $hasSnapshot = isset($changes[\App\Models\ActivityLog::SNAPSHOT]);
    unset($changes[\App\Models\ActivityLog::SNAPSHOT]);
    $changes = array_diff_key($changes, array_flip(\App\Models\ActivityLog::HIDDEN_FIELDS));
    $show = fn ($field, $v) => \App\Models\ActivityLog::displayValue($field, $v);
    // Нэмсэн үед зөвхөн шинэ утга; хоосон талбарыг алгасна
    $isCreate = $log->action === 'created';
    if ($isCreate) {
        $changes = array_filter($changes, fn ($c) => ! in_array($c['new'] ?? null, [null, ''], true));
    }
@endphp

@if ($changes)
    @php
        $rows = collect($changes)->map(fn ($c, $field) => [
            'field' => $field,
            'label' => \App\Models\ActivityLog::fieldLabel($field),
            'old' => $isCreate || ! array_key_exists('old', (array) $c) ? null : $show($field, $c['old'] ?? null),
            'new' => $show($field, $c['new'] ?? null),
            'diff' => ! $isCreate && array_key_exists('old', (array) $c),
        ]);
        $first = $rows->first();
    @endphp

    {{-- Нэг мөр хураангуй --}}
    <div class="flex min-w-0 items-center gap-2 text-xs">
        @if ($isCreate)
            @php $key = $rows->filter(fn ($r) => in_array($r['field'], ['category', 'department', 'assigned_to'], true))->pluck('new'); @endphp
            <span class="min-w-0 truncate text-slate-600">
                <span class="text-slate-400">{{ $rows->count() }} талбар</span>@if ($key->isNotEmpty()) · {{ $key->implode(' · ') }}@endif
            </span>
        @else
            <span class="min-w-0 truncate">
                <span class="text-slate-400">{{ $first['label'] }}:</span>
                @if ($first['diff'])
                    <span class="text-rose-500 line-through">{{ \Illuminate\Support\Str::limit($first['old'], 30) }}</span>
                    <span class="text-slate-400">→</span>
                @endif
                <span class="font-medium text-emerald-700">{{ \Illuminate\Support\Str::limit($first['new'], 30) }}</span>
            </span>
            @if ($rows->count() > 1)
                <span class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">+{{ $rows->count() - 1 }}</span>
            @endif
        @endif
    </div>

    {{-- Цонхны агуулга: толгой (үйлдэл, төхөөрөмж, хэн, хэзээ) + бүлэглэсэн талбарууд --}}
    @php
        $grouped = collect(\App\Models\ActivityLog::FIELD_GROUPS)
            ->map(fn ($fields) => $rows->filter(fn ($r) => in_array($r['field'], $fields, true))->sortBy(fn ($r) => array_search($r['field'], $fields, true)))
            ->put('Бусад', $rows->reject(fn ($r) => in_array($r['field'], array_merge(...array_values(\App\Models\ActivityLog::FIELD_GROUPS)), true)))
            ->filter(fn ($g) => $g->isNotEmpty());
        preg_match('/^(.*)\s\[([^\]]+)\]$/u', (string) $log->subject_label, $m);
        $dlgName = $m[1] ?? $log->subject_label;
        $dlgCode = isset($m[2]) && ! str_contains($m[1], $m[2]) ? $m[2] : null;
    @endphp
    <template data-log-detail>
        <div class="border-b border-slate-100 px-6 py-4 pr-14">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $log->actionColor() }}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ $log->actionLabel() }}</span>
            <h3 class="!mb-0 mt-2 text-lg font-bold text-slate-900">{{ $dlgName }}</h3>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                @if ($dlgCode)<span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-slate-700">{{ $dlgCode }}</span>@endif
                <span>{{ $log->user?->name ?? 'Систем' }}</span>
                <span>{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                <span>{{ $rows->count() }} {{ $isCreate ? 'талбар' : 'өөрчлөлт' }}</span>
            </div>
        </div>
        <div class="max-h-[65vh] space-y-5 overflow-y-auto px-6 py-5">
            @foreach ($grouped as $group => $items)
                <section>
                    <h4 class="!mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $group }}</h4>
                    <dl class="grid gap-2 sm:grid-cols-2">
                        @foreach ($items as $r)
                            <div class="min-w-0 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2">
                                <dt class="text-[11px] text-slate-500">{{ $r['label'] }}</dt>
                                <dd class="mt-0.5 break-words text-sm">
                                    @if ($r['diff'])
                                        <span class="block text-xs text-rose-500 line-through">{{ $r['old'] }}</span>
                                        <span class="font-semibold text-emerald-700">{{ $r['new'] }}</span>
                                    @else
                                        <span class="font-medium text-slate-900">{{ $r['new'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    </template>
@elseif ($hasSnapshot)
    <span class="text-xs text-slate-500">Бүрмөсөн устгагдсан · өмнөх мэдээлэл хадгалагдсан</span>
@elseif (isset(\App\Models\ActivityLog::ACTION_NOTES[$log->action]))
    <span class="text-xs text-slate-500">{{ \App\Models\ActivityLog::ACTION_NOTES[$log->action] }}</span>
@else
    <span class="text-xs text-slate-300">—</span>
@endif
