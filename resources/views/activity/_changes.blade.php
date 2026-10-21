{{-- $log: App\Models\ActivityLog --}}
@php
    $changes = $log->changes ?? [];
    // Бүрмөсөн устгахаас өмнөх бүтэн хуулбарыг жагсаахгүй (сэргээхэд л ашиглана)
    $hasSnapshot = isset($changes[\App\Models\ActivityLog::SNAPSHOT]);
    unset($changes[\App\Models\ActivityLog::SNAPSHOT]);
    $show = fn ($v) => \Illuminate\Support\Str::limit(($v === null || $v === '') ? '—' : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)), 60);
@endphp

@if ($changes)
    <ul class="space-y-0.5 text-xs">
        @foreach ($changes as $field => $c)
            <li>
                <span class="text-slate-500">{{ \App\Models\ActivityLog::fieldLabel($field) }}:</span>
                @if ($log->action === 'created' || ! array_key_exists('old', (array) $c))
                    <span class="text-slate-700">{{ $show($c['new'] ?? null) }}</span>
                @else
                    <span class="text-red-600 line-through">{{ $show($c['old'] ?? null) }}</span>
                    <span class="text-slate-400">→</span>
                    <span class="font-medium text-emerald-700">{{ $show($c['new'] ?? null) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@elseif ($hasSnapshot)
    <span class="text-xs text-slate-500">Устгахаас өмнөх мэдээлэл хадгалагдсан</span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
