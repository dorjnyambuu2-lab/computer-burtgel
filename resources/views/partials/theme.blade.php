{{-- Dark/Light горим: <head>-д (CSS-ээс өмнө) ачаалж, хуудас цагаанаар анивчихаас сэргийлнэ.
     Сонголт localStorage('theme') = 'dark' | 'light'; сонгоогүй бол компьютерийн тохиргоог дагана. --}}
<script>
(function () {
    var root = document.documentElement, media = window.matchMedia('(prefers-color-scheme: dark)');
    function stored() { try { return localStorage.getItem('theme'); } catch (e) { return null; } }
    function apply() { var t = stored(); root.classList.toggle('dark', t ? t === 'dark' : media.matches); }
    apply();
    // Сонголт хийгээгүй үед системийн горим солигдвол дагана
    media.addEventListener && media.addEventListener('change', function () { if (!stored()) apply(); });
    window.toggleTheme = function () {
        var dark = !root.classList.contains('dark');
        try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        root.classList.toggle('dark', dark);
    };
})();
</script>
