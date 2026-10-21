{{--
    Баталгаа дуусах огноог автоматаар бодно: худалдан авсан огноо + баталгааны хугацаа (сар).
    Хувьсагч: $purchaseId, $monthsId, $untilId (input-уудын id)
    - Хугацаа сонгох эсвэл огноо өөрчлөхөд дуусах огноо шинэчлэгдэнэ
    - «Баталгаагүй» бол дуусах огноо хоосорно
    - Дуусах огноог гараар өөрчилбөл хугацаа «Өөр (гараар)» болно
--}}
<script>
(function () {
    const purchase = document.getElementById(@json($purchaseId));
    const months = document.getElementById(@json($monthsId));
    const until = document.getElementById(@json($untilId));
    if (!purchase || !months || !until) return;

    // 2024-01-31 + 1 сар → 2024-02-29 (сарын сүүлийн өдрөөс хэтрэхгүй)
    function addMonths(date, count) {
        const p = date.split('-').map(Number);
        const target = new Date(Date.UTC(p[0], p[1] - 1 + count, 1));
        const lastDay = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
        target.setUTCDate(Math.min(p[2], lastDay));
        return target.toISOString().slice(0, 10);
    }

    function recalc() {
        if (months.value === '') {
            until.value = '';
        } else if (months.value !== 'manual' && purchase.value) {
            until.value = addMonths(purchase.value, parseInt(months.value, 10));
        }
    }

    months.addEventListener('change', recalc);
    purchase.addEventListener('change', recalc);
    until.addEventListener('input', function () { months.value = until.value ? 'manual' : ''; });

    // Шинэ бүртгэлд: хугацаа сонгогдсон, дуусах огноо хоосон бол шууд бодно
    if (!until.value && months.value && months.value !== 'manual') recalc();
})();
</script>
