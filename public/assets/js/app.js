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

    // ── Dark mode toggle ──────────────────────────────────────────────────
    const themeToggle = document.getElementById('ks-theme-toggle');
    const themeIcon   = document.getElementById('ks-theme-icon');
    const html        = document.getElementById('html-root');

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        html.setAttribute('data-bs-theme', theme);
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
        if (themeToggle) {
            themeToggle.title = theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre';
        }
    }

    if (themeToggle) {
        // Sync icon with current theme (already applied by inline script)
        applyTheme(html.getAttribute('data-theme') || 'light');

        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-theme') || 'light';
            const next    = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('ks-theme', next);
            applyTheme(next);
        });
    }

});
