@extends('layouts.app')
@section('title', 'Засах')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Засах: {{ $computer->name }}</h1>
<form method="POST" action="{{ route('computers.update', $computer) }}">
    @csrf @method('PUT')
    @include('computers._form')
</form>
@endsection
