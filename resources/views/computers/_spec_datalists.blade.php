{{--
    Сонголтын жагсаалтууд (datalist). Талбарт жагсаалтаас сонгох эсвэл гараар бичнэ.
    Гараар бичсэн шинэ утга хадгалахад жагсаалтад автоматаар нэмэгдэнэ (App\Support\SpecSuggestions).
    id: sg-brand-computer, sg-model-computer, sg-cpu, sg-ram_gb, sg-storage, sg-os, sg-department, sg-location,
        sg-brand-{ангилал}, sg-model-{ангилал}, sg-specs-{ангилал}
--}}
@php $sg = \App\Support\SpecSuggestions::suggest(); @endphp
<datalist id="sg-brand-computer">@foreach ($sg['brand'] as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
<datalist id="sg-model-computer">@foreach ($sg['model'] as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
@foreach (['cpu', 'ram_gb', 'storage', 'os', 'department', 'location'] as $f)
    <datalist id="sg-{{ $f }}">@foreach ($sg[$f] as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
@endforeach
@foreach (array_keys(\App\Support\SpecSuggestions::ITEM_BRANDS) as $cat)
    <datalist id="sg-brand-{{ $cat }}">@foreach (\App\Support\SpecSuggestions::itemBrands($cat) as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
    <datalist id="sg-model-{{ $cat }}">@foreach (\App\Support\SpecSuggestions::itemModels($cat) as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
    <datalist id="sg-specs-{{ $cat }}">@foreach (\App\Support\SpecSuggestions::itemSpecs($cat) as $o)<option value="{{ $o }}"></option>@endforeach</datalist>
@endforeach

<script>
// Жагсаалтад байхгүй утга бичихэд: тухайн жагсаалтад шууд нэмж (бусад мөрөнд сонгож болно),
// «шинэ» гэж тэмдэглэнэ. Хадгалахад байнгын жагсаалтад орно.
(function () {
    if (window.__specLearn) return;
    window.__specLearn = true;

    function hint(input, show) {
        let el = input.parentElement.querySelector(':scope > [data-new-hint]');
        if (!show) { if (el) el.remove(); return; }
        if (!el) {
            el = document.createElement('p');
            el.dataset.newHint = '';
            el.className = 'mt-1 text-[11px] text-emerald-700';
            el.textContent = '＋ Шинэ утга: хадгалахад жагсаалтад нэмэгдэнэ';
            input.after(el);
        }
    }

    document.addEventListener('change', function (e) {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || !input.list) return;
        const value = input.value.trim().replace(/\s+/g, ' ');
        if (value === '') { hint(input, false); return; }

        const options = Array.from(input.list.options);
        const match = options.find(function (o) { return o.value.toLowerCase() === value.toLowerCase(); });

        if (match && !match.dataset.added) {
            // Жагсаалтад байгаа: үсгийн хэлбэрийг жагсаалтынхаар болгоно («dell» → «Dell»)
            input.value = match.value;
            hint(input, false);
            return;
        }
        if (!match) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.dataset.added = '1';
            input.list.appendChild(opt);
        }
        hint(input, true);
    });
})();
</script>
