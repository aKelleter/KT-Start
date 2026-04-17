(function () {
    const csrf = document.getElementById('btnCheckPending').dataset.csrf;

    const allRows = Array.from(document.querySelectorAll('tr[data-id]'));
    const allIds  = allRows.map(tr => tr.dataset.id);
    const total   = allIds.length;

    const getPendingIds = () =>
        Array.from(document.querySelectorAll('tr[data-id][data-status=""]'))
             .map(tr => tr.dataset.id);

    const btnPending = document.getElementById('btnCheckPending');
    const btnAll     = document.getElementById('btnCheckAll');
    const btnStop    = document.getElementById('btnStop');

    let stopRequested = false;
    let isRunning     = false;

    // ── Démarrage d'une vérification ─────────────────────────────────────────
    function startCheck(ids) {
        if (isRunning) return;
        isRunning     = true;
        stopRequested = false;

        btnPending.classList.add('d-none');
        btnAll.classList.add('d-none');
        btnStop.classList.remove('d-none');
        document.getElementById('checkProgress').classList.remove('d-none');

        const alreadyDone = total - ids.length;
        updateProgress(alreadyDone, alreadyDone);

        checkNext(ids, alreadyDone);
    }

    // ── Vérification URL par URL ─────────────────────────────────────────────
    async function checkNext(ids, done) {
        if (ids.length === 0 || stopRequested) {
            onRunEnd(ids.length === 0);
            return;
        }

        const id  = ids[0];
        const rest = ids.slice(1);
        const fd  = new FormData();
        fd.append('_csrf', csrf);
        fd.append('id', id);

        try {
            const r    = await fetch('?action=bookmark_check_single', { method: 'POST', body: fd });
            const data = await r.json();

            if (data.ok) {
                document.querySelectorAll(`tr[data-id="${id}"]`).forEach(tr => {
                    tr.dataset.status = data.status;
                });

                const cls = data.status === 'ok'
                    ? 'text-bg-success'
                    : (data.status === 'redirect' ? 'text-bg-warning' : 'text-bg-danger');

                document.querySelectorAll(`.ks-http-code[data-id="${id}"]`).forEach(el => {
                    const label = data.http_code > 0 ? data.http_code : (data.status === 'timeout' ? 'Timeout' : '—');
                    el.innerHTML = `<span class="badge ${cls}">${label}</span>`;
                });
                document.querySelectorAll(`.ks-checked-at[data-id="${id}"]`).forEach(el => {
                    el.textContent = data.checked_at.substring(0, 16);
                });
            }
        } catch (e) {
            document.querySelectorAll(`tr[data-id="${id}"]`).forEach(tr => {
                tr.dataset.status = 'timeout';
            });
        }

        updateProgress(done + 1, done + 1);
        checkNext(rest, done + 1);
    }

    // ── Mise à jour des compteurs résumé ────────────────────────────────────
    function syncCounters() {
        const rows = Array.from(document.querySelectorAll('tr[data-id]'));
        const st   = { ok: 0, error: 0, timeout: 0, redirect: 0 };
        rows.forEach(tr => { if (st[tr.dataset.status] !== undefined) st[tr.dataset.status]++; });
        document.getElementById('countOk').textContent        = st.ok;
        document.getElementById('countDead').textContent      = st.error + st.timeout;
        document.getElementById('countRedirect').textContent  = st.redirect;
        document.getElementById('countUnchecked').textContent = getPendingIds().length;
    }

    function updateProgress(done) {
        const pct = Math.round(done / total * 100);
        document.getElementById('progressBar').style.width   = pct + '%';
        document.getElementById('progressLabel').textContent = `${done} / ${total}`;
        syncCounters();
    }

    // ── Recheck individuel ───────────────────────────────────────────────────
    document.querySelectorAll('.ks-recheck-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id  = this.dataset.id;
            const row = document.querySelector(`tr[data-id="${id}"]`);

            this.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75rem;height:.75rem"></span>';
            this.disabled  = true;

            const fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', id);

            try {
                const r    = await fetch('?action=bookmark_check_single', { method: 'POST', body: fd });
                const data = await r.json();

                if (data.ok) {
                    const cls   = data.status === 'ok' ? 'text-bg-success'
                                : data.status === 'redirect' ? 'text-bg-warning'
                                : 'text-bg-danger';
                    const label = data.http_code > 0 ? data.http_code
                                : (data.status === 'timeout' ? 'Timeout' : '—');

                    document.querySelectorAll(`.ks-http-code[data-id="${id}"]`).forEach(el => {
                        el.innerHTML = `<span class="badge ${cls}">${label}</span>`;
                    });
                    document.querySelectorAll(`.ks-checked-at[data-id="${id}"]`).forEach(el => {
                        el.textContent = data.checked_at.substring(0, 16);
                    });
                    if (row) row.dataset.status = data.status;

                    if (data.status === 'ok' || data.status === 'redirect') {
                        this.innerHTML = '<i class="bi bi-check-lg text-success" style="font-size:.85rem"></i>';
                        this.classList.replace('btn-outline-secondary', 'btn-link');
                        this.disabled = true;
                    } else {
                        this.innerHTML = '<i class="bi bi-arrow-repeat" style="font-size:.85rem"></i>';
                        this.disabled  = false;
                    }

                    syncCounters();
                } else {
                    this.innerHTML = '<i class="bi bi-arrow-repeat" style="font-size:.85rem"></i>';
                    this.disabled  = false;
                }
            } catch {
                this.innerHTML = '<i class="bi bi-arrow-repeat" style="font-size:.85rem"></i>';
                this.disabled  = false;
            }
        });
    });

    // ── Exclure / Réintégrer un lien ─────────────────────────────────────────
    document.querySelectorAll('.ks-skip-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id      = this.dataset.id;
            const skipping = this.dataset.skip !== '1';

            this.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75rem;height:.75rem"></span>';
            this.disabled  = true;

            const fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', id);

            try {
                const r    = await fetch('?action=bookmark_toggle_skip', { method: 'POST', body: fd });
                const data = await r.json();

                if (data.ok) {
                    location.reload();
                } else {
                    this.innerHTML = skipping
                        ? '<i class="bi bi-slash-circle" style="font-size:.85rem"></i>'
                        : '<i class="bi bi-arrow-counterclockwise" style="font-size:.85rem"></i>';
                    this.disabled = false;
                }
            } catch {
                this.innerHTML = skipping
                    ? '<i class="bi bi-slash-circle" style="font-size:.85rem"></i>'
                    : '<i class="bi bi-arrow-counterclockwise" style="font-size:.85rem"></i>';
                this.disabled = false;
            }
        });
    });

    function onRunEnd(completed) {
        isRunning = false;
        btnStop.classList.add('d-none');

        const remaining = getPendingIds().length;

        if (completed) {
            btnPending.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Revérifier';
            btnPending.classList.remove('d-none');
            setTimeout(() => location.reload(), 800);
        } else {
            if (remaining > 0) {
                btnPending.innerHTML = `<i class="bi bi-play-fill me-1"></i>Continuer (${remaining} restant${remaining > 1 ? 's' : ''})`;
                btnAll.classList.remove('d-none');
            } else {
                btnPending.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Tout revérifier';
            }
            btnPending.classList.remove('d-none');
        }
    }

    document.getElementById('btnResetStatus')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('resetStatusModal')).show();
    });
    document.getElementById('btnResetStatusConfirm')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('resetStatusModal')).hide();
        document.getElementById('resetStatusForm').submit();
    });

    btnPending.addEventListener('click', () => startCheck(getPendingIds()));
    btnAll.addEventListener('click', () => {
        document.querySelectorAll('tr[data-id]').forEach(tr => tr.dataset.status = '');
        startCheck(allIds);
    });
    btnStop.addEventListener('click', () => { stopRequested = true; });

    // ── Sélection en lot (suppression) ──────────────────────────────────────
    function updateActionBar() {
        const n   = document.querySelectorAll('.ks-dead-check:checked').length;
        const bar = document.getElementById('deadActionsBar');
        bar.classList.toggle('d-none', n === 0);
        document.getElementById('selectedCount').textContent = n;
    }

    document.querySelectorAll('.ks-dead-check').forEach(cb => cb.addEventListener('change', updateActionBar));

    document.querySelectorAll('.ks-check-section').forEach(cb => {
        cb.addEventListener('change', function () {
            document.querySelectorAll('.ks-dead-check').forEach(c => { c.checked = this.checked; });
            updateActionBar();
        });
    });

    document.getElementById('btnDeselectAll')?.addEventListener('click', () => {
        document.querySelectorAll('.ks-dead-check, .ks-check-section').forEach(c => c.checked = false);
        updateActionBar();
    });

    document.getElementById('btnDeleteDeadSelected')?.addEventListener('click', () => {
        const n = document.querySelectorAll('.ks-dead-check:checked').length;
        document.getElementById('deleteDeadModalCount').textContent = n;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteDeadModal')).show();
    });

    document.getElementById('btnDeleteDeadConfirm')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteDeadModal')).hide();
        document.getElementById('deadLinksForm').submit();
    });

    // ── Sélection en lot (exclusion) ─────────────────────────────────────────
    function updateSkipBar() {
        const n   = document.querySelectorAll('.ks-skip-check:checked').length;
        const bar = document.getElementById('skipActionsBar');
        bar.classList.toggle('d-none', n === 0);
        document.getElementById('skipSelectedCount').textContent = n;
    }

    document.querySelectorAll('.ks-skip-check').forEach(cb => cb.addEventListener('change', updateSkipBar));

    document.querySelectorAll('.ks-check-skip-section').forEach(cb => {
        cb.addEventListener('change', function () {
            const table = this.closest('table');
            table.querySelectorAll('.ks-skip-check').forEach(c => c.checked = this.checked);
            updateSkipBar();
        });
    });

    document.getElementById('btnDeselectSkip')?.addEventListener('click', () => {
        document.querySelectorAll('.ks-skip-check, .ks-check-skip-section').forEach(c => c.checked = false);
        updateSkipBar();
    });

    document.getElementById('btnSkipSelected')?.addEventListener('click', async function () {
        const ids = Array.from(document.querySelectorAll('.ks-skip-check:checked')).map(c => c.value);
        if (!ids.length) return;

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="width:.75rem;height:.75rem"></span>Exclusion…';

        for (const id of ids) {
            const fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', id);
            try {
                await fetch('?action=bookmark_toggle_skip', { method: 'POST', body: fd });
            } catch (_) { /* continue */ }
        }

        location.reload();
    });

    // ── Sélection redirects ──────────────────────────────────────────────────
    function updateRedirectBar() {
        const n   = document.querySelectorAll('.ks-redirect-check:checked').length;
        const bar = document.getElementById('redirectActionsBar');
        bar.classList.toggle('d-none', n === 0);
        document.getElementById('redirectSelectedCount').textContent = n;
    }

    document.querySelectorAll('.ks-redirect-check').forEach(cb => cb.addEventListener('change', updateRedirectBar));

    document.querySelector('.ks-check-redirect-section')?.addEventListener('change', function () {
        document.querySelectorAll('.ks-redirect-check').forEach(c => c.checked = this.checked);
        updateRedirectBar();
    });

    document.getElementById('btnDeselectRedirects')?.addEventListener('click', () => {
        document.querySelectorAll('.ks-redirect-check, .ks-check-redirect-section').forEach(c => c.checked = false);
        updateRedirectBar();
    });

    // ── Revérification en lot ────────────────────────────────────────────────
    function updateRecheckBar() {
        const n   = document.querySelectorAll('.ks-recheck-check:checked').length;
        const bar = document.getElementById('recheckActionsBar');
        bar.classList.toggle('d-none', n === 0);
        document.getElementById('recheckSelectedCount').textContent = n;
    }

    document.querySelectorAll('.ks-recheck-check').forEach(cb => cb.addEventListener('change', updateRecheckBar));

    document.querySelectorAll('.ks-check-recheck-section').forEach(cb => {
        cb.addEventListener('change', function () {
            const table = this.closest('table');
            table.querySelectorAll('.ks-recheck-check').forEach(c => c.checked = this.checked);
            updateRecheckBar();
        });
    });

    document.getElementById('btnDeselectRecheck')?.addEventListener('click', () => {
        document.querySelectorAll('.ks-recheck-check, .ks-check-recheck-section').forEach(c => c.checked = false);
        updateRecheckBar();
    });

    document.getElementById('btnRecheckSelected')?.addEventListener('click', async function () {
        const ids = Array.from(document.querySelectorAll('.ks-recheck-check:checked')).map(c => c.value);
        if (!ids.length) return;

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75rem;height:.75rem"></span>';

        for (const id of ids) {
            const fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', id);
            try {
                const r    = await fetch('?action=bookmark_check_single', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.ok) {
                    const tr = document.querySelector(`tr[data-id="${id}"]`);
                    if (tr) tr.dataset.status = data.status;
                    const cls   = data.status === 'ok' ? 'text-bg-success'
                                : data.status === 'redirect' ? 'text-bg-warning' : 'text-bg-danger';
                    const label = data.http_code > 0 ? data.http_code
                                : (data.status === 'timeout' ? 'Timeout' : '—');
                    document.querySelectorAll(`.ks-http-code[data-id="${id}"]`).forEach(el => {
                        el.innerHTML = `<span class="badge ${cls}">${label}</span>`;
                    });
                    document.querySelectorAll(`.ks-checked-at[data-id="${id}"]`).forEach(el => {
                        el.textContent = data.checked_at.substring(0, 16);
                    });
                }
            } catch (_) { /* continue */ }
        }

        location.reload();
    });

    // ── Mise à jour des URLs redirigées ──────────────────────────────────────
    document.getElementById('btnUpdateRedirects')?.addEventListener('click', async function () {
        const btn = this;
        const ids = Array.from(document.querySelectorAll('.ks-redirect-check:checked')).map(c => c.value);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mise à jour…';

        for (const id of ids) {
            const fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('id', id);

            try {
                const r    = await fetch('?action=bookmark_follow_redirect', { method: 'POST', body: fd });
                const data = await r.json();

                if (data.ok) {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        const link   = row.querySelector('a');
                        const urlDiv = row.querySelector('.text-muted.small.text-truncate');
                        if (link)   { link.href = data.new_url; link.textContent = link.textContent; }
                        if (urlDiv) { urlDiv.textContent = data.new_url; }
                    }
                }
            } catch (e) { /* continue */ }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>Mettre à jour les URLs';
        setTimeout(() => location.reload(), 800);
    });
})();
