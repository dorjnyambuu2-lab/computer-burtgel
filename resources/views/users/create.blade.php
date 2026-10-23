@extends('layouts.app')
@section('title', 'Хэрэглэгч нэмэх')

@section('content')
<div class="mb-5">
    <div class="text-xs text-slate-500">Удирдлага / Хэрэглэгч</div>
    <h1 class="!mb-0 text-xl font-bold">{{ auth()->user()->isBranchScoped() ? 'Шинэ ажилтан нэмэх' : 'Шинэ хэрэглэгч нэмэх' }}</h1>
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