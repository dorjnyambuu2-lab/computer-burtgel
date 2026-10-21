{{-- Тоног төхөөрөмж нэмэх / засах форм --}}
@extends('layouts.app')
@section('title', $equipment->exists ? 'Тоног төхөөрөмж засах' : 'Тоног төхөөрөмж бүртгэх')

@section('content')
<div class="page-top"><div><p class="eyebrow">Тоног төхөөрөмж / {{ $equipment->exists ? $equipment->code : 'Шинэ' }}</p>
    <h1>{{ $equipment->exists ? 'Тоног төхөөрөмж засах' : 'Тоног төхөөрөмж бүртгэх' }}</h1></div></div>

<form method="POST" class="card" style="padding:20px"
      action="{{ $equipment->exists ? route('equipment.update', $equipment) : route('equipment.store') }}">
    @csrf
    @if($equipment->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="item" label="Нэр" :value="$equipment->item" :required="true" :full="true" />
        <x-field name="category" label="Төрөл" :value="$equipment->category" :options="App\Models\Equipment::CATEGORIES" :required="true" />
        <x-field name="brand" label="Үйлдвэрлэгч" :value="$equipment->brand" :required="true" />
        <x-field name="model" label="Загвар" :value="$equipment->model" />
        <x-field name="serial" label="Serial Number" :value="$equipment->serial" />
        <x-field name="price" label="Үнэ (₮)" type="number" :value="$equipment->price" />
        <x-field name="location" label="Байршил" :value="$equipment->location" :required="true" />
        <x-field name="status" label="Төлөв" :value="$equipment->status" :options="App\Models\Equipment::STATUSES" :required="true" />
        <x-field name="employee_id" label="Хариуцсан ажилтан" :value="$equipment->employee_id" :options="$employees" />
    </div>
    <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:20px">
        <a class="secondary-btn" href="{{ route('equipment.index') }}">Цуцлах</a>
        <button class="primary-btn" type="submit"><i data-lucide="save"></i>Хадгалах</button>
    </div>
</form>
@endsection
