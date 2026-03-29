document.addEventListener('DOMContentLoaded', () => {

    // ── Back to top ───────────────────────────────────────────────────────
    const btn = document.getElementById('back-to-top');
    if (btn) {
        const toggle = () => btn.classList.toggle('visible', window.scrollY > 300);
        window.addEventListener('scroll', toggle, { passive: true });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        toggle();
    }

    // ── Auto-dismiss flash ────────────────────────────────────────────────
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            alert.classList.add('flash-hide');
            setTimeout(() => alert.remove(), 450);
        }, 4000);
    });

});
