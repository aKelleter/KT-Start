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
    // Durée configurable depuis Admin → Paramètres (0 = désactivé), portée par
    // data-flash-duration sur <body>. Exposée globalement pour les messages
    // injectés dynamiquement en JS (ex. admin/lists.js).
    const flashDuration = parseInt(document.body.dataset.flashDuration || '3000', 10);

    function autoDismissFlash(alert) {
        if (!flashDuration || flashDuration <= 0) {
            return;
        }
        setTimeout(() => {
            alert.classList.add('flash-hide');
            setTimeout(() => alert.remove(), 450);
        }, flashDuration);
    }
    window.ksAutoDismissFlash = autoDismissFlash;

    document.querySelectorAll('.alert-dismissible').forEach(autoDismissFlash);

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
        applyTheme(html.getAttribute('data-theme') || 'light');

        themeToggle.addEventListener('click', () => {
            const current = html.getAttribute('data-theme') || 'light';
            const next    = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('ks-theme', next);
            applyTheme(next);
        });
    }

    // ── Toggle liste dropdown/boutons ─────────────────────────────────────
    const listNavToggle = document.getElementById('btnListNavToggle');
    if (listNavToggle) {
        const KEY  = 'ks-list-nav';
        let mode   = document.documentElement.dataset.listNav || 'buttons';

        function applyListNavIcon(m) {
            if (m === 'buttons') {
                listNavToggle.innerHTML = '<i class="bi bi-menu-button-wide-fill"></i>';
                listNavToggle.title = 'Passer en liste déroulante';
            } else {
                listNavToggle.innerHTML = '<i class="bi bi-collection"></i>';
                listNavToggle.title = 'Passer en boutons';
            }
        }

        applyListNavIcon(mode);

        listNavToggle.addEventListener('click', function () {
            const next    = mode === 'buttons' ? 'dropdown' : 'buttons';
            const leaving = document.getElementById(mode === 'buttons' ? 'ks-list-nav-buttons' : 'ks-list-filter-dropdown');

            const DURATION = 400;
            const EASING   = 'opacity ' + DURATION + 'ms ease, transform ' + DURATION + 'ms ease';

            function doSwitch() {
                mode = next;
                localStorage.setItem(KEY, mode);
                document.documentElement.dataset.listNav = mode;
                applyListNavIcon(mode);

                const entering = document.getElementById(mode === 'buttons' ? 'ks-list-nav-buttons' : 'ks-list-filter-dropdown');
                if (entering) {
                    entering.style.opacity   = '0';
                    entering.style.transform = 'translateY(-5px)';
                    entering.getBoundingClientRect();
                    entering.style.transition = EASING;
                    entering.style.opacity    = '1';
                    entering.style.transform  = 'translateY(0)';
                    setTimeout(() => {
                        entering.style.transition = '';
                        entering.style.opacity    = '';
                        entering.style.transform  = '';
                    }, DURATION);
                }
            }

            if (leaving) {
                leaving.style.transition = 'opacity .15s ease, transform .15s ease';
                leaving.style.opacity    = '0';
                leaving.style.transform  = 'translateY(-5px)';
                setTimeout(() => {
                    leaving.style.transition = '';
                    leaving.style.opacity    = '';
                    leaving.style.transform  = '';
                    doSwitch();
                }, 150);
            } else {
                doSwitch();
            }
        });
    }

    // ── Recherche liste (dropdown toolbar) ───────────────────────────────
    const listSearch = document.querySelector('.ks-list-search');
    if (listSearch) {
        listSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.ks-list-dropdown-items a[data-list-name]').forEach(a => {
                a.style.display = a.dataset.listName.includes(q) ? '' : 'none';
            });
        });
        listSearch.addEventListener('click', e => e.stopPropagation());
    }

});
