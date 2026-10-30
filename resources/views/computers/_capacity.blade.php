{{--
    RAM, санах ойг цэгцтэй харуулах: нийт хэмжээ том, доор нь модуль / диск бүр шошгоор.
    RAM: «18 GB» + [16GB] [2GB]; Диск: «1088 GB» + [SSD 512GB] [HDD 320GB] [M.2 256GB]
--}}
@php
    if ($field === 'ram') {
        $total = $computer->ram_gb ? $computer->ram_gb.' GB' : null;
        $chips = $computer->ram_modules
            ? collect(preg_split('/\s*\+\s*/', $computer->ram_modules))->map(fn ($m) => [null, $m])->all()
            : [];
    } else {
        $gb = \App\Support\SpecSuggestions::storageTotalGb($computer->storage);
        $total = $gb !== null ? rtrim(rtrim(number_format($gb, 1, '.', ''), '0'), '.').' GB' : null;
        $chips = \App\Support\SpecSuggestions::storageChips($computer->storage);
        // Ганц диск: хэмжээ нь нийттэй ижил тул зөвхөн төрөл (SSD)
        if (count($chips) === 1 && $chips[0][0]) {
            $chips = [[null, $chips[0][0]]];
        }
    }
@endphp
@if ($total)
    <span class="text-sm font-semibold text-slate-900">{{ $total }}</span>
    @if ($chips)
        <span class="mt-1.5 flex flex-wrap gap-1.5">
            @foreach ($chips as [$kind, $size])
                <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-700">
                    @if ($kind)<span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $kind }}</span>@endif
                    {{ $size }}
                </span>
            @endforeach
        </span>
    @endif
@else
    {{-- Хэмжээ танигдахгүй (хуучин чөлөөт бичвэр) бол байгаагаар нь --}}
    <span class="text-sm font-medium text-slate-900">{{ $field === 'ram' ? $computer->ramLabel() : $computer->storage }}</span>
@endif
