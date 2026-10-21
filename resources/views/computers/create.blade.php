@extends('layouts.app')
@section('title', 'Шинэ компьютер')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Шинэ компьютер бүртгэх</h1>
<form method="POST" action="{{ route('computers.store') }}">
    @csrf
    @include('computers._form')
</form>
@endsection
