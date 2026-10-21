{{-- Компьютерийн show хуудсанд: @include('computers._history', ['computer' => $computer]) --}}
@if (auth()->user()->canManageComputers())
    @php $logs = $computer->activities()->with('user')->limit(30)->get(); @endphp

    <div class="mt-6 rounded-xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-4 py-3 font-semibold">Өөрчлөлтийн түүх</div>

        @if ($logs->isEmpty())
            <p class="px-4 py-6 text-sm text-slate-400">Түүх байхгүй байна.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($logs as $log)
                    <li class="flex gap-4 px-4 py-3 text-sm">
                        <div class="w-36 shrink-0 text-slate-500">
                            <div>{{ $log->created_at?->format('Y-m-d H:i') }}</div>
                            <div class="text-xs">{{ $log->user?->name ?? 'Систем' }}</div>
                        </div>
                        <div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $log->actionColor() }}">{{ $log->actionLabel() }}</span>
                            <div class="mt-1">@include('activity._changes', ['log' => $log])</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
