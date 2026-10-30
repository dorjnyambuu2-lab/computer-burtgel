{{-- Алба, нэгжийн сонголтын нийтлэг JS (<script> дотор include хийнэ): computers/_form, users/_form --}}
// Сонголтыг салбараар (data-branch-off) болон нэгжээр (data-unit-off) нууна; нуугдсан нь сонгогдсон бол арилгана
window.orgApply = window.orgApply || function (select) {
    Array.from(select.options).forEach(function (o) {
        if (!o.dataset.branch) return; // «— … сонгох —», хуучин утга
        o.hidden = o.disabled = o.dataset.branchOff === '1' || o.dataset.unitOff === '1';
    });
    if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
};
// Салбар → Нэгж → Алба: нэгж сонгоход тухайн нэгжийн болон нэгжгүй албууд л; алба сонгоход нэгж нь бөглөгдөнө
(function () {
    const unit = document.querySelector('select#unit[data-org-select]');
    const dept = document.querySelector('select#department[data-org-select]');
    if (!unit || !dept || dept.dataset.unitLinked) return;
    dept.dataset.unitLinked = '1';

    function filter() {
        const chosen = unit.disabled ? '' : unit.value; // шинэ нэгж бичиж байвал бүх алба
        Array.from(dept.options).forEach(function (o) {
            if (!o.dataset.branch) return;
            o.dataset.unitOff = chosen && chosen !== '__new__' && o.dataset.unit && o.dataset.unit !== chosen ? '1' : '';
        });
        window.orgApply(dept);
    }
    // Нэгжийн жагсаалт болон «шинэ нэгж» талбарын өөрчлөлт
    unit.parentElement.addEventListener('change', filter);
    dept.addEventListener('change', function () {
        const o = dept.selectedOptions[0];
        if (!o || !o.dataset.unit || unit.disabled || unit.value) return;
        const match = Array.from(unit.options).find(function (u) { return u.value === o.dataset.unit && !u.disabled; });
        if (match) { unit.value = match.value; filter(); }
    });
    filter();
})();
