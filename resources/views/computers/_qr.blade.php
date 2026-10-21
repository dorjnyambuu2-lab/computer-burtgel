{{-- Компьютерийн show хуудсанд: @include('computers._qr', ['computer' => $computer]) --}}
<div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
    <div class="mx-auto inline-block">{!! \App\Support\Qr::svg($computer->qrContent(), 140) !!}</div>
    <p class="mt-2 font-mono text-sm font-semibold tracking-wider text-slate-800">{{ $computer->qr_code }}</p>
    <p class="text-xs text-slate-500">Давтагдашгүй QR дугаар · «QR уншуулах»-аар уншуулахад энэ хуудас нээгдэнэ</p>
    <a href="{{ route('computers.label', $computer) }}" target="_blank"
       class="mt-2 inline-block rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">QR шошго хэвлэх</a>
</div>
