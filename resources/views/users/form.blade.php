{{-- Хэрэглэгч нэмэх / засах форм. Засах үед нууц үгийг хоосон орхивол өөрчлөгдөхгүй --}}
@extends('layouts.app')
@section('title', $user->exists ? 'Хэрэглэгч засах' : 'Хэрэглэгч нэмэх')

@section('content')
<div class="page-top"><div><p class="eyebrow">Хэрэглэгч / {{ $user->exists ? $user->name : 'Шинэ' }}</p>
    <h1>{{ $user->exists ? 'Хэрэглэгч засах' : 'Хэрэглэгч нэмэх' }}</h1></div></div>

<form method="POST" class="card" style="padding:20px"
      action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="name" label="Нэр" :value="$user->name" :required="true" />
        <x-field name="email" label="Имэйл" type="email" :value="$user->email" :required="true" />
        <x-field name="role" label="Эрх" :value="$user->role" :options="App\Models\User::ROLES" :required="true" />
        <x-field name="status" label="Төлөв" :value="$user->status" :options="App\Models\User::STATUSES" :required="true" />
        {{-- Нууц үгийн талбарт хуучин утгыг хэзээ ч буцааж харуулахгүй тул value-г null өгнө --}}
        <x-field name="password" :label="$user->exists ? 'Шинэ нууц үг (өөрчлөхгүй бол хоосон)' : 'Нууц үг (8+ тэмдэгт)'" type="password" :required="! $user->exists" :full="true" />
    </div>
    <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:20px">
        <a class="secondary-btn" href="{{ route('users.index') }}">Цуцлах</a>
        <button class="primary-btn" type="submit"><i data-lucide="save"></i>Хадгалах</button>
    </div>
</form>
@endsection
