@extends('layouts.app')
@section('title', 'Мэдэгдэл')
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="!mb-0 text-xl font-bold">Мэдэгдэл</h1>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Бүгдийг уншсан болгох</button>
            </form>
        @endif
    </div>

    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @forelse ($notifications as $n)
            <li>
                <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-5 py-4 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-blue-50/60' }}">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-blue-600' }}"></span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-slate-900">{{ $n->data['title'] ?? 'Мэдэгдэл' }}</span>
                        @if (filled($n->data['body'] ?? null))
                            <span class="mt-0.5 block whitespace-pre-line text-sm text-slate-600">{{ $n->data['body'] }}</span>
                        @endif
                        <span class="mt-1 block text-xs text-slate-400">{{ $n->created_at->format('Y-m-d H:i') }} · {{ $n->created_at->locale('mn')->diffForHumans() }}</span>
                    </span>
                </a>
            </li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-400">Мэдэгдэл алга.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection
