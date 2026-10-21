{{-- Тоног төхөөрөмжийн жагсаалт --}}
@extends('layouts.app')
@section('title', 'Тоног төхөөрөмж')

@section('content')
<div class="page-top">
    <div><p class="eyebrow">Хөрөнгийн бүртгэл / Дагалдах тоног төхөөрөмж</p><h1>Тоног төхөөрөмж</h1></div>
    <div class="section-tools"><a href="{{ route('equipment.create') }}" class="primary-btn"><i data-lucide="plus"></i>Тоног төхөөрөмж бүртгэх</a></div>
</div>

<form method="GET" class="card filter-bar">
    <input class="filter-input" type="search" name="q" value="{{ request('q') }}" placeholder="Хайх">
    <select class="filter-select" name="category" onchange="this.form.submit()">
        <option value="">Ангиллаар шүүх</option>
        @foreach(App\Models\Equipment::CATEGORIES as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach
    </select>
    <select class="filter-select" name="status" onchange="this.form.submit()">
        <option value="">Төлөвөөр шүүх</option>
        @foreach(App\Models\Equipment::STATUSES as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach
    </select>
    <button class="secondary-btn" type="submit">Хайх</button>
    <a class="text-button" href="{{ route('equipment.index') }}">Шүүлтүүр цэвэрлэх</a>
</form>

<section class="card table-card">
    <div class="table-wrap"><table>
        <thead><tr><th>Нэр</th><th>Төрөл</th><th>Үйлдвэрлэгч</th><th>Загвар</th><th>Serial Number</th><th>Үнэ</th><th>Байршил</th><th>Төлөв</th><th>Хариуцсан ажилтан</th><th>Үйлдэл</th></tr></thead>
        <tbody>
        @forelse($equipment as $e)
            <tr>
                <td><strong>{{ $e->item }}</strong></td><td>{{ $e->category }}</td><td>{{ $e->brand }}</td>
                <td>{{ $e->model ?: '—' }}</td><td>{{ $e->serial ?: '—' }}</td><td>{{ $e->price_text }}</td>
                <td>{{ $e->location }}</td><td><x-pill :status="$e->status" /></td><td>{{ $e->employee?->name ?? '—' }}</td>
                <td>
                    <a class="text-button" href="{{ route('equipment.edit', $e) }}">Засах</a>
                    <x-delete-form :action="route('equipment.destroy', $e)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="10"><div class="empty-state">Илэрц олдсонгүй</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="table-footer"><span>Нийт {{ $equipment->total() }}</span><span>{{ $equipment->links() }}</span></div>
</section>
@endsection
