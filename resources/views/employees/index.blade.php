@extends('layouts.app')

@section('title', 'Хэрэглэгчид')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Хэрэглэгчид</h1>
        <a href="{{ route('users.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Хэрэглэгч нэмэх</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Нэр</th>
                    <th class="px-4 py-3 font-medium">Нэвтрэх нэр</th>
                    <th class="px-4 py-3 font-medium">Имэйл</th>
                    <th class="px-4 py-3 font-medium">Эрх</th>
                    <th class="px-4 py-3 font-medium">Төлөв</th>
                    <th class="px-4 py-3 text-right font-medium">Үйлдэл</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3 font-medium">{{ $user->username }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $user->roleBadgeClass() }}">
                                {{ $user->roleLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_active)
                                <span class="text-emerald-600">Идэвхтэй</span>
                            @else
                                <span class="text-red-600">Идэвхгүй</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:underline">Засах</a>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="ml-3 inline"
                                      onsubmit="return confirm('Устгах уу?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Устгах</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection