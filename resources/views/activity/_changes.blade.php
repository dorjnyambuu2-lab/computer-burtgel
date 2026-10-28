{{-- $log: App\Models\ActivityLog. Өөрчлөлт олон бол хумигдана: эхний 2-г харуулж, дарахад бүгд задарна --}}
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
    $preview = 2;
@endphp

@if ($changes)
    @php
        $rows = collect($changes)->map(fn ($c, $field) => [
            'label' => \App\Models\ActivityLog::fieldLabel($field),
            'old' => $isCreate || ! array_key_exists('old', (array) $c) ? null : $show($field, $c['old'] ?? null),
            'new' => $show($field, $c['new'] ?? null),
            'diff' => ! $isCreate && array_key_exists('old', (array) $c),
        ]);
        $renderRow = function ($r) {
            $html = '<div class="flex min-w-0 gap-2 py-0.5">'
                .'<dt class="w-36 shrink-0 text-slate-400">'.e($r['label']).'</dt><dd class="min-w-0 break-words text-slate-700">';
            if ($r['diff']) {
                $html .= '<span class="text-rose-500 line-through">'.e(\Illuminate\Support\Str::limit($r['old'], 60)).'</span>'
                    .' <span class="text-slate-400">→</span> '
                    .'<span class="font-medium text-emerald-700">'.e(\Illuminate\Support\Str::limit($r['new'], 60)).'</span>';
            } else {
                $html .= e(\Illuminate\Support\Str::limit($r['new'], 80));
            }

            return $html.'</dd></div>';
        };
    @endphp

    @if ($rows->count() <= $preview + 1)
        <dl class="text-xs">@foreach ($rows as $r){!! $renderRow($r) !!}@endforeach</dl>
    @else
        {{-- Хумигдсан: эхний 2 + «Бүгдийг харах»; задарсан: «Хураах» + бүх талбар --}}
        <details class="group text-xs">
            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <dl class="group-open:hidden">@foreach ($rows->take($preview) as $r){!! $renderRow($r) !!}@endforeach</dl>
                <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:bg-slate-200 group-open:mt-0">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="transition group-open:rotate-180" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    <span class="group-open:hidden">Бүгдийг харах (+{{ $rows->count() - $preview }})</span>
                    <span class="hidden group-open:inline">Хураах · {{ $rows->count() }} талбар</span>
                </span>
            </summary>
            <dl class="mt-1.5 rounded-lg bg-slate-50 px-3 py-2">@foreach ($rows as $r){!! $renderRow($r) !!}@endforeach</dl>
        </details>
    @endif
@elseif ($hasSnapshot)
    <span class="text-xs text-slate-500">Устгахаас өмнөх мэдээлэл хадгалагдсан</span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
