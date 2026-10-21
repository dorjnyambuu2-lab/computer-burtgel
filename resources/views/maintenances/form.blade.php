{{-- Засварын бүртгэл нэмэх / засах форм --}}
@extends('layouts.app')
@section('title', $maintenance->exists ? 'Засвар засах' : 'Засварын бүртгэл нэмэх')

@section('content')
<div class="page-top"><div><p class="eyebrow">Засвар / {{ $maintenance->exists ? $maintenance->code : 'Шинэ' }}</p>
    <h1>{{ $maintenance->exists ? 'Засвар засах' : 'Засварын бүртгэл нэмэх' }}</h1></div></div>

<form method="POST" class="card" style="padding:20px"
      action="{{ $maintenance->exists ? route('maintenances.update', $maintenance) : route('maintenances.store') }}">
    @csrf
    @if($maintenance->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="computer_id" label="Компьютер" :value="$maintenance->computer_id" :options="$computers" :required="true" :full="true" />
        <x-field name="problem" label="Асуудлын тайлбар" :value="$maintenance->problem" :required="true" :full="true" />
        <x-field name="repair_date" label="Засварын огноо" type="date" :value="$maintenance->repair_date?->format('Y-m-d')" :required="true" />
        <x-field name="technician" label="Хариуцсан техникч" :value="$maintenance->technician" :required="true" />
        <x-field name="cost" label="Засварын зардал (₮)" type="number" :value="$maintenance->cost" />
        <x-field name="status" label="Төлөв" :value="$maintenance->status" :options="App\Models\Maintenance::STATUSES" :required="true" />
        <x-field name="completed_date" label="Дууссан огноо" type="date" :value="$maintenance->completed_date?->format('Y-m-d')" />
    </div>
    <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:20px">
        <a class="secondary-btn" href="{{ route('maintenances.index') }}">Цуцлах</a>
        <button class="primary-btn" type="submit"><i data-lucide="save"></i>Хадгалах</button>
    </div>
</form>
@endsection
