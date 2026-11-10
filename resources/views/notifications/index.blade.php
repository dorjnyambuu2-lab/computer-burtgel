@extends('layouts.app')
@section('title', 'Мэдэгдэл')
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="!mb-0 text-2xl font-bold text-slate-900">Мэдэгдэл</h1>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Бүгдийг уншсан болгох</button>
            </form>
        @endif
    </div>

    {{-- Ижил төрлийн, нэг хуудас руу заасан мэдэгдлүүд нэг мөр (NotificationController::index) --}}
    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @forelse ($groups as $g)
            @php $n = $g->latest; @endphp
            <li data-notification-group class="{{ $g->unread ? 'bg-blue-50/60' : '' }}">
                <a href="{{ route('notifications.open', $g->count > 1 ? ['id' => $n->id, 'all' => 1] : $n->id) }}" class="flex gap-3 px-5 py-4 transition hover:bg-slate-50">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $g->unread ? 'bg-blue-600' : 'bg-transparent' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $n->data['title'] ?? 'Мэдэгдэл' }}</span>
                            @if ($g->count > 1)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600" title="Ижил төрлийн мэдэгдэл">{{ $g->count }} мэдэгдэл</span>
                            @endif
                            @if ($g->count > 1 && $g->unread)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $g->unread }} шинэ</span>
                            @endif
                        </span>
                        @if (filled($n->data['body'] ?? null))
                            <span class="mt-0.5 block whitespace-pre-line text-sm text-slate-600">{{ $n->data['body'] }}</span>
                        @endif
                        <span class="mt-1 block text-xs text-slate-400">{{ $n->created_at->format('Y-m-d H:i') }} · {{ $n->created_at->locale('mn')->diffForHumans() }}</span>
                    </span>
                </a>
                @if ($g->count > 1)
                    {{-- Бүлгийн өмнөх мэдэгдлүүд --}}
                    <details class="group/older px-5 pb-3 pl-10">
                        <summary class="cursor-pointer list-none text-xs font-medium text-blue-600 hover:underline">
                            <span class="group-open/older:hidden">Өмнөх {{ $g->count - 1 }}-ийг харах</span>
                            <span class="hidden group-open/older:inline">Нуух</span>
                        </summary>
                        <ul class="mt-2 space-y-1 border-l-2 border-slate-200 pl-3">
                            @foreach ($g->items->skip(1) as $older)
                                <li>
                                    <a href="{{ route('notifications.open', $older->id) }}" class="block rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-50">
                                        <span class="{{ $older->read_at ? 'text-slate-600' : 'font-semibold text-slate-900' }}">{{ $older->data['title'] ?? 'Мэдэгдэл' }}</span>
                                        @if (filled($older->data['body'] ?? null))
                                            <span class="block whitespace-pre-line text-xs text-slate-500">{{ $older->data['body'] }}</span>
                                        @endif
                                        <span class="block text-[11px] text-slate-400">{{ $older->created_at->format('Y-m-d H:i') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-400">Мэдэгдэл алга.</li>
        @endforelse
    </ul>

    @if ($truncated)
        <p class="mt-3 text-center text-xs text-slate-400">Сүүлийн 1000 мэдэгдлийг харуулж байна.</p>
    @endif
    <div class="mt-4">{{ $groups->links() }}</div>
</div>
@endsection
