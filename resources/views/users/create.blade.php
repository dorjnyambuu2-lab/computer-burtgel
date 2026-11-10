@extends('layouts.app')
@section('title', 'Хэрэглэгч нэмэх')

@section('content')
<div class="mx-auto mb-5 max-w-3xl">
    <a href="{{ url('/users') }}" class="inline-flex items-center gap-1 text-sm font-medium !text-slate-500 hover:!text-slate-800">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        {{ auth()->user()->isBranchScoped() ? 'Ажилтан' : 'Хэрэглэгч' }}
    </a>
    <h1 class="!mb-0 mt-2 text-2xl font-bold text-slate-900">{{ auth()->user()->isBranchScoped() ? 'Шинэ ажилтан нэмэх' : 'Шинэ хэрэглэгч нэмэх' }}</h1>
</div>
<form method="POST" action="{{ url('/users') }}">
    @csrf
    {{-- «Төхөөрөмж олгох»-оос ирсэн бол хадгалсны дараа тийш нь буцна --}}
    @if (request('return') === 'bulk-assign')
        <input type="hidden" name="return" value="bulk-assign">
    @endif
    @include('users._form')
</form>
@endsection