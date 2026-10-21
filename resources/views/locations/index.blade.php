{{-- Байршлын хяналт: өрөө бүрт хэдэн компьютер байгааг багтаамжтай нь харьцуулна --}}
@extends('layouts.app')
@section('title', 'Байршил')

@section('content')
<div class="page-top"><div><p class="eyebrow">Хөрөнгийн бүртгэл / Байршлын хяналт</p><h1>Байршил</h1></div></div>

<form method="GET" class="card filter-bar">
    <input class="filter-input" type="search" name="q" value="{{ request('q') }}" placeholder="Барилга эсвэл өрөөгөөр хайх">
    <button class="secondary-btn" type="submit">Хайх</button>
    <a class="text-button" href="{{ route('locations.index') }}">Цэвэрлэх</a>
</form>

<section class="location-grid">
    @foreach($locations as $l)
        <article class="card location-card">
            <h3>{{ $l->building }}</h3>
            <p>{{ $l->room }} — {{ $l->computers_count }} компьютер</p>
            {{-- Зурвасын өргөн = ашиглалтын хувь --}}
            <div class="occupancy"><span style="width:{{ $l->percent }}%"></span></div>
            <div class="occupancy-text"><span>Компьютерийн тоо: {{ $l->computers_count }}</span><span>{{ $l->percent }}% ашиглалт</span></div>
        </article>
    @endforeach
</section>

<section class="card table-card" style="margin-top:18px">
    <div class="card-head"><div><h2>Байршлын жагсаалт</h2><p class="card-subtitle">Өрөөний багтаамж ба төхөөрөмжийн тоо</p></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Барилга</th><th>Өрөө</th><th>Компьютерийн тоо</th><th>Багтаамж</th><th>Ашиглалт</th></tr></thead>
        <tbody>
        @forelse($locations as $l)
            <tr>
                <td><strong>{{ $l->building }}</strong></td><td>{{ $l->room }}</td><td>{{ $l->computers_count }}</td><td>{{ $l->capacity }}</td>
                <td><div style="display:flex;align-items:center;gap:9px"><div class="occupancy" style="width:100px;margin:0"><span style="width:{{ $l->percent }}%"></span></div>{{ $l->percent }}%</div></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty-state">Илэрц олдсонгүй</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
