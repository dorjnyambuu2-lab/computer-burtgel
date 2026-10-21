{{-- Тохиргоо — одоогоор зөвхөн харагдах байдал (өгөгдлийн санд хадгалахгүй) --}}
@extends('layouts.app')
@section('title', 'Тохиргоо')

@section('content')
<div class="page-top"><div><p class="eyebrow">Удирдлага / Системийн тохиргоо</p><h1>Тохиргоо</h1></div></div>

<section class="settings-grid">
    <article class="card settings-card">
        <h2>Байгууллагын мэдээлэл</h2>
        <div class="form-grid" style="margin-top:13px">
            <div class="form-field full"><label>Байгууллагын нэр</label><input value="Их сургуулийн мэдээллийн технологийн төв"></div>
            <div class="form-field"><label>Холбоо барих имэйл</label><input value="it@university.edu.mn"></div>
            <div class="form-field"><label>Цагийн бүс</label><select><option>Улаанбаатар (UTC+8)</option></select></div>
        </div>
    </article>
    <article class="card settings-card">
        <h2>Аюулгүй байдал</h2>
        <div class="form-grid" style="margin-top:13px">
            <div class="form-field full"><label>Session хугацаа</label><select><option>30 минут</option><option>60 минут</option></select></div>
        </div>
    </article>
</section>
<p class="card-subtitle" style="margin-top:14px">Тэмдэглэл: Тохиргоог хадгалах функцийг дараагийн алхамд `settings` хүснэгт үүсгэж хэрэгжүүлж болно.</p>
@endsection
