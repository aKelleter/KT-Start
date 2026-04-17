// ── Data island ─────────────────────────────────────────────────────────────
const _ks = JSON.parse(document.getElementById('ks-data')?.textContent || '{}');

// ── Modal favori, suppression, fetch méta, doublon, liste picker, badge, tags ──
(function () {
    try {
    const modal       = document.getElementById('bookmarkModal');
    if (!modal) return;

    const form        = document.getElementById('bookmarkForm');
    const deleteForm  = document.getElementById('deleteForm');
    const folderSelect = document.getElementById('bmFolderId');

    function syncFolderOptionsByList(listId) {
        if (!folderSelect) return;
        const target = String(listId || '');
        [...folderSelect.options].forEach((opt, idx) => {
            if (idx === 0) {
                opt.hidden = false;
                opt.disabled = false;
                return;
            }
            const belongs = opt.dataset.listId === target;
            opt.hidden = !belongs;
            opt.disabled = !belongs;
        });

        const selected = folderSelect.options[folderSelect.selectedIndex];
        if (selected && selected.disabled) {
            folderSelect.value = '';
        }
    }

    modal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const mode = btn?.dataset.mode ?? 'add';

        document.getElementById('bookmarkModalTitle').textContent =
            mode === 'edit' ? 'Modifier le favori' : 'Ajouter un favori';
        document.getElementById('bmSubmitBtn').textContent =
            mode === 'edit' ? 'Enregistrer' : 'Ajouter';

        form.action = mode === 'edit' ? '?action=bookmark_update' : '?action=bookmark_store';

        document.getElementById('btnDelete').classList.toggle('d-none', mode !== 'edit');
        document.getElementById('btnSkipToggle').classList.toggle('d-none', mode !== 'edit');

        document.getElementById('bmDuplicateAlert').classList.add('d-none');

        if (mode === 'edit') {
            document.getElementById('bmId').value          = btn.dataset.id;
            document.getElementById('bmUrl').value         = btn.dataset.url;
            document.getElementById('bmHost').value        = btn.dataset.host;
            document.getElementById('bmTitle').value       = btn.dataset.title;
            document.getElementById('bmDescription').value = btn.dataset.description;
            document.getElementById('bmBadgeText').value   = btn.dataset.badgeText;
            document.getElementById('bmTags').value        = btn.dataset.tags;
            const listItem = document.querySelector(`#bmListItems .dropdown-item[data-value="${btn.dataset.listId}"]`);
            selectList(btn.dataset.listId, listItem?.dataset.label ?? listItem?.textContent.trim());
            syncFolderOptionsByList(btn.dataset.listId);
            folderSelect.value = btn.dataset.folderId || '';
            document.getElementById('bmVisibility').value  = btn.dataset.visibility;
            document.getElementById('deleteId').value      = btn.dataset.id;
            selectBadgeStyle(btn.dataset.badgeStyle);
            updateSkipBtn(parseInt(btn.dataset.checkSkip ?? '0', 10));
        } else {
            form.reset();
            document.getElementById('bmId').value = '';
            selectList('', '— Aucune —');
            syncFolderOptionsByList('');
            folderSelect.value = '';
            selectBadgeStyle('deepBlue');
        }
    });

    function updateSkipBtn(skip, id = null) {
        const btn   = document.getElementById('btnSkipToggle');
        const label = document.getElementById('btnSkipLabel');
        const icon  = btn.querySelector('i');
        if (skip) {
            btn.classList.replace('btn-outline-secondary', 'btn-warning');
            icon.className = 'bi bi-slash-circle-fill me-1';
            label.textContent = 'Réintégrer dans la vérif.';
            btn.title = 'Réintégrer ce favori dans la vérification automatique des liens';
        } else {
            btn.classList.replace('btn-warning', 'btn-outline-secondary');
            icon.className = 'bi bi-slash-circle me-1';
            label.textContent = 'Exclure de la vérif.';
            btn.title = 'Exclure ce favori de la vérification automatique des liens';
        }
        btn.dataset.currentSkip = skip ? '1' : '0';

        if (id) {
            document.querySelectorAll('[data-id="' + id + '"] .ks-link-dot').forEach(dot => {
                dot.style.display = skip ? 'none' : '';
            });
        }
    }

    document.getElementById('btnSkipToggle').addEventListener('click', async function () {
        const id   = document.getElementById('bmId').value;
        const csrf = document.querySelector('#bookmarkForm input[name="_csrf"]').value;
        this.disabled = true;
        try {
            const res  = await fetch('?action=bookmark_toggle_skip', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: '_csrf=' + encodeURIComponent(csrf) + '&id=' + encodeURIComponent(id),
            });
            const data = await res.json();
            if (data.ok) {
                updateSkipBtn(data.skip, id);
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.disabled = false;
        }
    });

    let deleteFromEditModal = false;

    function openDeleteConfirm(id, title, fromEdit) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteConfirmTitle').textContent = title || '';
        deleteFromEditModal = !!fromEdit;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal')).show();
    }

    document.getElementById('deleteConfirmBtn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'))?.hide();
        if (deleteFromEditModal) {
            bootstrap.Modal.getInstance(document.getElementById('bookmarkModal'))?.hide();
        }
        deleteForm.submit();
    });

    document.getElementById('btnDelete').addEventListener('click', function () {
        const title = document.getElementById('bmTitle').value
                   || document.getElementById('bmUrl').value;
        openDeleteConfirm(document.getElementById('deleteId').value, title, true);
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.ks-quick-delete');
        if (!btn) return;
        const row = btn.closest('[data-id]');
        const title = row?.querySelector('a[href]')?.textContent.trim()
                   || btn.dataset.deleteId;
        openDeleteConfirm(btn.dataset.deleteId, title, false);
    });

    document.getElementById('btnFetchMeta').addEventListener('click', async function () {
        const url = document.getElementById('bmUrl').value.trim();
        if (!url) return;

        const spinner = document.getElementById('fetchSpinner');
        spinner.classList.remove('d-none');
        this.disabled = true;

        try {
            const res  = await fetch('?action=bookmark_fetch_meta&url=' + encodeURIComponent(url));
            const data = await res.json();

            if (!data.error) {
                if (data.title)       document.getElementById('bmTitle').value       = data.title;
                if (data.host)        document.getElementById('bmHost').value        = data.host;
                if (data.description) document.getElementById('bmDescription').value = data.description;
                if (!document.getElementById('bmBadgeText').value && data.title) {
                    document.getElementById('bmBadgeText').value = data.title.substring(0, 30);
                }
            }
        } catch (err) {
            console.error(err);
        } finally {
            spinner.classList.add('d-none');
            this.disabled = false;
        }
    });

    document.getElementById('bmUrl').addEventListener('blur', async function () {
        const url   = this.value.trim();
        const alert = document.getElementById('bmDuplicateAlert');
        alert.classList.add('d-none');
        if (!url) return;

        const excludeId = document.getElementById('bmId').value;
        const params = '?action=bookmark_check_duplicate&url=' + encodeURIComponent(url)
                     + (excludeId ? '&exclude_id=' + encodeURIComponent(excludeId) : '');
        try {
            const data = await fetch(params).then(r => r.json());
            if (data.duplicate) {
                document.getElementById('bmDuplicateTitle').textContent = data.title;
                document.getElementById('bmDuplicateList').textContent  = data.list_name ? ' — ' + data.list_name : '';
                alert.classList.remove('d-none');
            }
        } catch (e) { /* silencieux */ }
    });

    function selectList(value, label) {
        document.getElementById('bmListId').value  = value;
        document.getElementById('bmListLabel').textContent = label || '— Aucune —';
        syncFolderOptionsByList(value);
        document.querySelectorAll('#bmListItems .dropdown-item').forEach(a => {
            a.classList.toggle('active', a.dataset.value === String(value));
        });
        bootstrap.Dropdown.getInstance(document.getElementById('bmListBtn'))?.hide();
    }

    document.getElementById('bmListItems').addEventListener('click', function (e) {
        const item = e.target.closest('.dropdown-item');
        if (!item) return;
        e.preventDefault();
        selectList(item.dataset.value, item.dataset.label ?? item.textContent.trim());
    });

    document.getElementById('bmListSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#bmListItems .dropdown-item').forEach(a => {
            a.style.display = (a.dataset.label ?? a.textContent).toLowerCase().includes(q) ? '' : 'none';
        });
    });

    document.getElementById('bmListSearch').addEventListener('click', e => e.stopPropagation());

    function selectBadgeStyle(style) {
        document.querySelectorAll('.ks-badge-style-radio').forEach(radio => {
            const swatch = radio.closest('.ks-color-swatch');
            radio.checked = radio.value === style;
            swatch.classList.toggle('selected', radio.checked);
        });
    }

    document.querySelectorAll('.ks-badge-style-radio').forEach(radio => {
        radio.addEventListener('change', () => selectBadgeStyle(radio.value));
    });

    (function () {
        const input = document.getElementById('bmTags');
        if (!input) return;
        const tags = _ks.tags ?? [];
        if (!tags.length) return;

        const dl = document.createElement('datalist');
        dl.id = '_dl_bmTags';
        input.setAttribute('list', dl.id);
        document.body.appendChild(dl);

        function refresh() {
            const val    = input.value;
            const comma  = val.lastIndexOf(',');
            const prefix = comma >= 0 ? val.substring(0, comma + 1) + ' ' : '';
            const done   = val.split(',').map(t => t.trim()).filter(Boolean);
            dl.innerHTML = tags
                .filter(t => !done.includes(t))
                .map(t => `<option value="${prefix}${t}"></option>`)
                .join('');
        }
        input.addEventListener('input', refresh);
        input.addEventListener('focus', refresh);
    })();
    } catch (e) { console.error('Bookmarks modal init:', e); }
})();

// ── Drag & drop et gestion des dossiers ──────────────────────────────────────
(function () {
    const csrf     = _ks.csrf ?? '';
    const view     = _ks.view ?? 'badges';
    const sort     = _ks.sort ?? '';
    const readOnly = _ks.readOnly ?? false;
    const listId   = _ks.listId ?? null;

    const needsSortable = sort === 'position' || view === 'explorer';
    const needsFolders  = !readOnly && listId !== null;
    if (!needsSortable && !needsFolders) return;

    async function saveOrder(ids) {
        try {
            await fetch('?action=bookmark_reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: csrf, ids }),
            });
        } catch (e) {
            console.error('Erreur sauvegarde ordre :', e);
        }
    }

    function makeSortable(el, childSelector) {
        if (!el || typeof Sortable === 'undefined') return;
        new Sortable(el, {
            handle: '.ks-drag-handle',
            animation: 150,
            ghostClass: 'ks-sortable-ghost',
            chosenClass: 'ks-sortable-chosen',
            onEnd() {
                const ids = [...el.querySelectorAll(childSelector)]
                    .map(item => parseInt(item.dataset.id, 10))
                    .filter(Boolean);
                saveOrder(ids);
            },
        });
    }

    if (view !== 'explorer') {
        const folderGrids = document.querySelectorAll('.ks-badges-grid[data-folder-id]');
        if (folderGrids.length > 0 && typeof Sortable !== 'undefined') {
            const currentListId = parseInt(
                (document.getElementById('ksFolderList') || folderGrids[0]).dataset.listId || '0', 10
            );

            const folderList = document.getElementById('ksFolderList');
            if (folderList) {
                new Sortable(folderList, {
                    handle: '.ks-drag-handle',
                    animation: 150,
                    ghostClass: 'ks-sortable-ghost',
                    chosenClass: 'ks-sortable-chosen',
                    async onEnd() {
                        const items = [...folderList.querySelectorAll(':scope > .ks-folder-group')]
                            .map((el, pos) => ({ type: 'folder', id: parseInt(el.dataset.folderId, 10), pos }))
                            .filter(i => i.id);
                        try {
                            await fetch('?action=bookmark_explorer_reorder', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    _csrf: csrf,
                                    list_id: currentListId,
                                    parent_id: null,
                                    items: items.map(i => ({ type: i.type, id: i.id })),
                                }),
                            });
                        } catch (e) { console.error('Erreur ordre dossiers:', e); }
                    },
                });
            }

            let pendingBadgeSave = new Set();
            let badgeSaveTimer = null;

            function scheduleBadgeSave(container) {
                pendingBadgeSave.add(container);
                clearTimeout(badgeSaveTimer);
                badgeSaveTimer = setTimeout(() => {
                    const toSave = [...pendingBadgeSave];
                    pendingBadgeSave.clear();
                    toSave.forEach(saveBadgesContainer);
                }, 300);
            }

            async function saveBadgesContainer(container) {
                const folderId = container.dataset.folderId;
                const parentId = (folderId === '' || folderId === undefined) ? null : parseInt(folderId, 10);
                const items = [...container.querySelectorAll(':scope > .ks-badge')]
                    .map(el => ({ type: 'bookmark', id: parseInt(el.dataset.id, 10) }))
                    .filter(i => i.id);
                try {
                    await fetch('?action=bookmark_explorer_reorder', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ _csrf: csrf, list_id: currentListId, parent_id: parentId, items }),
                    });
                } catch (e) { console.error('Erreur sauvegarde dossier badge:', e); }
            }

            function updateFolderCount(grid) {
                const folderId = grid.dataset.folderId;
                if (folderId === undefined) return;
                const allGrids = folderId === ''
                    ? document.querySelectorAll('.ks-badges-grid[data-folder-id=""]')
                    : document.querySelectorAll(`.ks-badges-grid[data-folder-id="${folderId}"]`);
                let total = 0;
                allGrids.forEach(g => { total += g.querySelectorAll(':scope > .ks-badge').length; });
                if (folderId !== '') {
                    const badge = document.querySelector(`[data-folder-count="${folderId}"]`);
                    if (badge) badge.textContent = total;
                }
            }

            let hoverTimer = null;
            let hoverOpenedTriggers = new Set();

            function cancelHoverOpen() {
                clearTimeout(hoverTimer);
                hoverTimer = null;
            }

            document.querySelectorAll('.ks-folder-group').forEach(group => {
                group.addEventListener('dragover', e => {
                    e.preventDefault();
                    const trigger = group.querySelector('[data-bs-toggle="collapse"]');
                    if (!trigger || !trigger.classList.contains('collapsed')) return;
                    if (hoverTimer) return;
                    hoverTimer = setTimeout(() => {
                        hoverTimer = null;
                        const targetId = (trigger.dataset.bsTarget || '').replace('#', '');
                        const collapseEl = targetId ? document.getElementById(targetId) : null;
                        if (!collapseEl) return;
                        hoverOpenedTriggers.add(trigger);
                        bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
                        collapseEl.querySelectorAll('.ks-badges-grid').forEach(g => {
                            if (!g.querySelector('.ks-badge:not(.ks-folder-badge)')) g.classList.add('ks-drop-target-empty');
                        });
                    }, 350);
                });
                group.addEventListener('dragleave', cancelHoverOpen);
            });

            folderGrids.forEach(grid => {
                new Sortable(grid, {
                    group: 'ks-badges',
                    handle: '.ks-drag-handle',
                    animation: 150,
                    ghostClass: 'ks-sortable-ghost',
                    chosenClass: 'ks-sortable-chosen',
                    onEnd(evt) {
                        cancelHoverOpen();
                        const droppedIntoCollapse = evt.to.closest('.collapse');
                        hoverOpenedTriggers.forEach(trigger => {
                            const targetId = (trigger.dataset.bsTarget || '').replace('#', '');
                            const collapseEl = targetId ? document.getElementById(targetId) : null;
                            if (collapseEl && collapseEl === droppedIntoCollapse) return;
                            if (collapseEl) {
                                bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
                                collapseEl.querySelectorAll('.ks-drop-target-empty').forEach(g => g.classList.remove('ks-drop-target-empty'));
                            }
                        });
                        hoverOpenedTriggers.clear();
                        scheduleBadgeSave(evt.to);
                        if (evt.from !== evt.to) {
                            scheduleBadgeSave(evt.from);
                            updateFolderCount(evt.to);
                            updateFolderCount(evt.from);
                        }
                        [evt.to, evt.from].forEach(g => {
                            const empty = g.querySelectorAll(':scope > .ks-badge').length === 0;
                            g.classList.toggle('ks-drop-target-empty', empty);
                        });
                    },
                });
            });
        } else {
            document.querySelectorAll('.ks-badges-grid').forEach(el => makeSortable(el, '.ks-badge'));
        }

        document.querySelectorAll('.ks-compact-list').forEach(el => makeSortable(el, '.ks-compact-item'));
        makeSortable(document.querySelector('table tbody'), 'tr');
    }

    const tree = document.getElementById('ksExplorerTree');
    if (tree) {
        async function saveExplorerContainer(container) {
            const treeListId = parseInt(tree.dataset.listId || '0', 10);
            if (!treeListId) return;
            const parentRaw = container.dataset.parentId;
            const parentId = parentRaw === '' ? null : parseInt(parentRaw, 10);
            const items = [...container.children]
                .filter(node => node.classList.contains('ks-explorer-node'))
                .map(node => ({ type: node.dataset.type, id: parseInt(node.dataset.id, 10) }))
                .filter(i => i.type && i.id);
            await fetch('?action=bookmark_explorer_reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: csrf, list_id: treeListId, parent_id: parentId, items }),
            });
        }
        document.querySelectorAll('.ks-explorer-dropzone').forEach(container => {
            new Sortable(container, {
                group: 'ks-explorer',
                handle: '.ks-drag-handle',
                animation: 150,
                ghostClass: 'ks-sortable-ghost',
                chosenClass: 'ks-sortable-chosen',
                onEnd(evt) {
                    const promises = [saveExplorerContainer(evt.to)];
                    if (evt.from && evt.from !== evt.to) promises.push(saveExplorerContainer(evt.from));
                    Promise.all(promises).catch(console.error);
                },
            });
        });
    }

    const folderCreateModalEl = document.getElementById('folderCreateModal');
    if (!folderCreateModalEl) return;

    const folderRenameModalEl = document.getElementById('folderRenameModal');
    const folderDeleteModalEl = document.getElementById('folderDeleteModal');
    const folderCreateInput  = document.getElementById('folderCreateModalInput');
    const folderRenameInput  = document.getElementById('folderRenameModalInput');

    let pendingCreateParentId = '';
    let pendingRenameId = '';
    let pendingDeleteId = '';

    folderCreateModalEl.addEventListener('shown.bs.modal', () => folderCreateInput?.focus());
    folderRenameModalEl.addEventListener('shown.bs.modal', () => folderRenameInput?.focus());

    folderCreateInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('folderCreateModalConfirm').click();
    });
    folderRenameInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('folderRenameModalConfirm').click();
    });

    document.getElementById('folderCreateModalConfirm').addEventListener('click', () => {
        const name = folderCreateInput.value.trim();
        if (!name) { folderCreateInput.focus(); return; }
        document.getElementById('folderCreateParentId').value = pendingCreateParentId;
        document.getElementById('folderCreateName').value = name;
        bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).hide();
        document.getElementById('folderCreateForm').submit();
    });

    document.getElementById('folderRenameModalConfirm').addEventListener('click', () => {
        const name = folderRenameInput.value.trim();
        if (!name) { folderRenameInput.focus(); return; }
        document.getElementById('folderRenameId').value = pendingRenameId;
        document.getElementById('folderRenameName').value = name;
        bootstrap.Modal.getOrCreateInstance(folderRenameModalEl).hide();
        document.getElementById('folderRenameForm').submit();
    });

    document.getElementById('folderDeleteModalConfirm').addEventListener('click', () => {
        document.getElementById('folderDeleteId').value = pendingDeleteId;
        bootstrap.Modal.getOrCreateInstance(folderDeleteModalEl).hide();
        document.getElementById('folderDeleteForm').submit();
    });

    function toggleExplorerFolder(node) {
        const childZone = node?.querySelector(':scope > .ks-explorer-dropzone');
        if (!childZone) return;
        const toggle = node.querySelector(':scope > .ks-explorer-row .ks-folder-toggle');
        const collapsed = childZone.classList.toggle('d-none');
        toggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle?.querySelector('i')?.classList.toggle('bi-caret-right-fill', collapsed);
        toggle?.querySelector('i')?.classList.toggle('bi-caret-down-fill', !collapsed);
    }

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('.ks-folder-toggle');
        if (toggle) {
            toggleExplorerFolder(toggle.closest('.ks-explorer-folder'));
            return;
        }

        if (!e.target.closest('button') && !e.target.closest('.ks-drag-handle')) {
            const row = e.target.closest('.ks-explorer-folder > .ks-explorer-row');
            if (row) {
                toggleExplorerFolder(row.closest('.ks-explorer-folder'));
                return;
            }
        }

        const createChild = e.target.closest('.ks-folder-create-child');
        if (createChild) {
            pendingCreateParentId = createChild.dataset.id || '';
            document.getElementById('folderCreateModalTitle').textContent = 'Créer un sous-dossier';
            document.getElementById('folderCreateModalHint').textContent = 'Dans ' + (createChild.dataset.name || 'ce dossier');
            folderCreateInput.value = '';
            bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).show();
            return;
        }

        const rename = e.target.closest('.ks-folder-rename');
        if (rename) {
            pendingRenameId = rename.dataset.id || '';
            folderRenameInput.value = rename.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(folderRenameModalEl).show();
            return;
        }

        const del = e.target.closest('.ks-folder-delete');
        if (del) {
            pendingDeleteId = del.dataset.id || '';
            document.getElementById('folderDeleteModalTitle').textContent = del.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(folderDeleteModalEl).show();
            return;
        }

        const createRoot = e.target.closest('#btnFolderCreateRoot');
        if (createRoot) {
            pendingCreateParentId = '';
            document.getElementById('folderCreateModalTitle').textContent = 'Créer un dossier';
            document.getElementById('folderCreateModalHint').textContent = 'À la racine de la liste';
            folderCreateInput.value = '';
            bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).show();
        }
    });
})();



// ── Taille des badges ─────────────────────────────────────────────────────────
(function () {
    if ((_ks.view ?? 'badges') !== 'badges') return;

    const SIZES  = [80, 105, 130, 160, 195, 230];
    const LABELS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    const KEY    = 'ks-badge-size';

    const btnSmaller = document.getElementById('btnBadgeSmaller');
    const btnLarger  = document.getElementById('btnBadgeLarger');
    const label      = document.getElementById('badgeSizeLabel');
    if (!btnSmaller || !btnLarger || !label) return;

    const saved = parseInt(localStorage.getItem(KEY) || '160', 10);
    let idx = SIZES.indexOf(saved);
    if (idx === -1) idx = SIZES.findIndex(s => s >= saved) ?? 2;
    idx = Math.max(0, Math.min(SIZES.length - 1, idx));

    function apply() {
        const size = SIZES[idx];
        document.documentElement.style.setProperty('--ks-badge-width', size + 'px');
        label.textContent        = LABELS[idx];
        btnSmaller.disabled      = idx === 0;
        btnLarger.disabled       = idx === SIZES.length - 1;
        localStorage.setItem(KEY, size);
    }

    btnSmaller.addEventListener('click', () => { if (idx > 0)                { idx--; apply(); } });
    btnLarger.addEventListener ('click', () => { if (idx < SIZES.length - 1) { idx++; apply(); } });

    apply();
})();

// ── Raccourci clavier N → modal d'ajout ──────────────────────────────────────
(function () {
    if (_ks.readOnly) return;

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'n' && e.key !== 'N') return;
        const tag = (document.activeElement?.tagName ?? '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select'
            || document.activeElement?.isContentEditable) return;
        const btn = document.querySelector('[data-bs-target="#bookmarkModal"][data-mode="add"]');
        if (btn) btn.click();
    });
})();
