{{-- Засвар үйлчилгээний жагсаалт --}}
@extends('layouts.app')
@section('title', 'Засвар үйлчилгээ')

@section('content')
<div class="page-top">
    <div><p class="eyebrow">Үйл ажиллагаа / Засварын удирдлага</p><h1>Засвар үйлчилгээ</h1></div>
    <div class="section-tools"><a href="{{ route('maintenances.create') }}" class="primary-btn"><i data-lucide="plus"></i>Засварын бүртгэл нэмэх</a></div>
</div>

{{-- Дээд талын 3 нэгтгэл карт ($summary-г controller тооцоолсон) --}}
<section class="summary-grid">
    <article class="card summary-card"><span>Нээлттэй засвар</span><strong>{{ $summary['open'] }}</strong><small class="warn">Техникчийн анхаарал шаардлагатай</small></article>
    <article class="card summary-card"><span>Энэ сард дууссан</span><strong>{{ $summary['completed'] }}</strong><small class="positive">Дууссан засвар</small></article>
    <article class="card summary-card"><span>Нийт засварын зардал</span><strong>{{ number_format($summary['cost']) }}₮</strong><small>Бүх хугацаа</small></article>
</section>

<form method="GET" class="card filter-bar" style="margin-top:16px">
    <input class="filter-input" type="search" name="q" value="{{ request('q') }}" placeholder="Хайх">
    <select class="filter-select" name="status" onchange="this.form.submit()">
        <option value="">Төлөвөөр шүүх</option>
        @foreach(App\Models\Maintenance::STATUSES as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach
    </select>
    <button class="secondary-btn" type="submit">Хайх</button>
    <a class="text-button" href="{{ route('maintenances.index') }}">Шүүлтүүр цэвэрлэх</a>
</form>

<section class="card table-card">
    <div class="table-wrap"><table>
        <thead><tr><th>Засварын дугаар</th><th>Компьютер</th><th>Асуудлын тайлбар</th><th>Засварын огноо</th><th>Техникч</th><th>Зардал</th><th>Төлөв</th><th>Дууссан огноо</th><th>Үйлдэл</th></tr></thead>
        <tbody>
        @forelse($maintenances as $m)
            <tr>
                <td><strong>{{ $m->code }}</strong></td>
                <td><a class="linkish" href="{{ route('computers.show', $m->computer) }}">{{ $m->computer->code }}</a></td>
                <td><strong>{{ $m->problem }}</strong></td><td>{{ $m->repair_date->format('Y-m-d') }}</td>
                <td>{{ $m->technician }}</td><td>{{ $m->cost_text }}</td><td><x-pill :status="$m->status" /></td>
                <td>{{ $m->completed_date?->format('Y-m-d') ?? '—' }}</td>
                <td>
                    <a class="text-button" href="{{ route('maintenances.edit', $m) }}">Засах</a>
                    <x-delete-form :action="route('maintenances.destroy', $m)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty-state">Илэрц олдсонгүй</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="table-footer"><span>Нийт {{ $maintenances->total() }}</span><span>{{ $maintenances->links() }}</span></div>
</section>
@endsection
