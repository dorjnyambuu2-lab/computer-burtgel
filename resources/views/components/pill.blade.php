{{-- Төлөвийг өнгөт шошго (pill) болгож харуулах component. Хэрэглээ: <x-pill :status="$computer->status" /> --}}
@props(['status'])

@php
    // Төлөв бүрт тохирох CSS class (app.css дотор тодорхойлсон)
    $cssClass = [
        'Ашиглаж байгаа' => 'use',        'Засварт байгаа' => 'maintenance',
        'Агуулахад байгаа' => 'storage',  'Хасагдсан' => 'retired',
        'Идэвхтэй' => 'active',           'Идэвхгүй' => 'inactive',
        'Чөлөөтэй' => 'maintenance',      'Дууссан' => 'completed',
        'Хийгдэж байна' => 'progress',    'Сэлбэг хүлээж байна' => 'parts',
        'Товлосон' => 'scheduled',
    ][$status] ?? 'storage'; // Жагсаалтад байхгүй бол 'storage' өнгө
@endphp

<span class="pill {{ $cssClass }}">{{ $status }}</span>
