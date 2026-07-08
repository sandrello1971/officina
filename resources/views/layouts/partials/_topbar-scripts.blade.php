{{-- Toggle vanilla (niente dipendenza da Alpine: scuola non lo carica) per il menu
     utente e la nav mobile. Un elemento con [data-toggle="#id"] apre/chiude il
     target; click fuori chiude tutto. --}}
<script>
(function () {
    function closeAll(except) {
        document.querySelectorAll('.topbar-menu.open, .topbar-nav.open').forEach(function (el) {
            if (el !== except) el.classList.remove('open');
        });
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-toggle]');
        if (trigger) {
            e.preventDefault();
            var target = document.querySelector(trigger.getAttribute('data-toggle'));
            if (target) {
                var willOpen = !target.classList.contains('open');
                closeAll(target);
                target.classList.toggle('open', willOpen);
            }
            return;
        }
        // Click fuori da menu/nav aperti → chiudi.
        if (!e.target.closest('.topbar-menu') && !e.target.closest('.topbar-nav')) {
            closeAll();
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
})();
</script>
