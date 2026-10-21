{{-- Устгах товч. Браузер зөвхөн GET/POST мэддэг тул @method('DELETE')-ээр DELETE болгон хуурна.
     Хэрэглээ: <x-delete-form :action="route('computers.destroy', $computer)" /> --}}
@props(['action'])

<form method="POST" action="{{ $action }}" style="display:inline"
      onsubmit="return confirm('Энэ бүртгэлийг устгахдаа итгэлтэй байна уу? Энэ үйлдлийг буцаах боломжгүй.')">
    @csrf               {{-- CSRF хамгаалалтын token (заавал хэрэгтэй) --}}
    @method('DELETE')
    <button type="submit" class="text-button" style="color:#b91c1c">Устгах</button>
</form>
