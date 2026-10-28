@extends('layouts.app')
@section('title', 'Засах')
@section('content')
<div class="mx-auto mb-5 max-w-5xl">
    <a href="{{ route('computers.show', $computer) }}" class="inline-flex items-center gap-1 text-sm font-medium !text-slate-500 hover:!text-slate-800">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        {{ $computer->name }}
    </a>
    <h1 class="!mb-0 mt-2 text-2xl font-bold text-slate-900">Засах</h1>
    <p class="mt-1 text-sm text-slate-500"><span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{{ $computer->inventory_number }}</span> · {{ $computer->name }}</p>
</div>
<form method="POST" action="{{ route('computers.update', $computer) }}">
    @csrf @method('PUT')
    @include('computers._form')
</form>
@endsection
