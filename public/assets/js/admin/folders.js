const _af = JSON.parse(document.getElementById('ks-af-data')?.textContent || '{}');

(function () {
    const listId        = _af.listId ?? 0;
    let pendingParentId = '';
    let pendingRenameId = '';
    let pendingDeleteId = '';

    const createModalEl = document.getElementById('afCreateModal');
    const renameModalEl = document.getElementById('afRenameModal');
    const deleteModalEl = document.getElementById('afDeleteModal');
    if (!createModalEl) return;

    const createInput = document.getElementById('afCreateInput');
    const renameInput = document.getElementById('afRenameInput');

    createModalEl.addEventListener('shown.bs.modal', () => createInput.focus());
    renameModalEl.addEventListener('shown.bs.modal', () => renameInput.focus());

    createInput.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('afCreateConfirm').click(); });
    renameInput.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('afRenameConfirm').click(); });

    document.getElementById('afCreateConfirm').addEventListener('click', () => {
        const name = createInput.value.trim();
        if (!name) { createInput.focus(); return; }
        document.getElementById('afCreateParentId').value = pendingParentId;
        document.getElementById('afCreateName').value      = name;
        bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
        document.getElementById('afCreateForm').submit();
    });

    document.getElementById('afRenameConfirm').addEventListener('click', () => {
        const name = renameInput.value.trim();
        if (!name) { renameInput.focus(); return; }
        document.getElementById('afRenameId').value   = pendingRenameId;
        document.getElementById('afRenameName').value = name;
        bootstrap.Modal.getOrCreateInstance(renameModalEl).hide();
        document.getElementById('afRenameForm').submit();
    });

    document.getElementById('afDeleteConfirm').addEventListener('click', () => {
        document.getElementById('afDeleteId').value = pendingDeleteId;
        bootstrap.Modal.getOrCreateInstance(deleteModalEl).hide();
        document.getElementById('afDeleteForm').submit();
    });

    function openCreate(parentId, parentName) {
        pendingParentId = parentId;
        const isChild = parentId !== '';
        document.getElementById('afCreateModalTitle').textContent = isChild ? 'Créer un sous-dossier' : 'Créer un dossier';
        document.getElementById('afCreateModalHint').textContent  = isChild ? 'Dans ' + parentName : 'À la racine de la liste';
        createInput.value = '';
        bootstrap.Modal.getOrCreateInstance(createModalEl).show();
    }

    document.addEventListener('click', e => {
        const createRoot = e.target.closest('#btnAfCreateRoot, #btnAfCreateRootEmpty');
        if (createRoot) { openCreate('', ''); return; }

        const createChild = e.target.closest('.ks-af-create-child');
        if (createChild) { openCreate(createChild.dataset.id, createChild.dataset.name || ''); return; }

        const rename = e.target.closest('.ks-af-rename');
        if (rename) {
            pendingRenameId     = rename.dataset.id;
            renameInput.value   = rename.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(renameModalEl).show();
            return;
        }

        const del = e.target.closest('.ks-af-delete');
        if (del) {
            pendingDeleteId = del.dataset.id;
            document.getElementById('afDeleteModalName').textContent = del.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
            return;
        }
    });

    // ── Drag & drop (SortableJS) ──────────────────────────────────────────────
    if (typeof Sortable === 'undefined') return;

    const statusEl = document.getElementById('afSaveStatus');
    let saveTimer  = null;

    function setStatus(msg, cls) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className   = 'small ms-3 flex-shrink-0 ' + cls;
    }

    function serializeTree() {
        const items = [];
        document.querySelectorAll('.ks-af-list').forEach(ul => {
            const raw      = ul.dataset.parentId;
            const parentId = (raw === '' || raw === undefined) ? null : parseInt(raw, 10);
            [...ul.querySelectorAll(':scope > .ks-af-node')].forEach((li, pos) => {
                items.push({ id: parseInt(li.dataset.id, 10), parent_id: parentId, pos });
            });
        });
        return items;
    }

    function doSave() {
        setStatus('Sauvegarde…', 'text-muted');
        fetch('?action=admin_folder_reorder', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ list_id: listId, folders: serializeTree() }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                setStatus('✓ Sauvegardé', 'text-success');
                setTimeout(() => setStatus('', 'text-muted'), 2000);
            } else {
                setStatus('Erreur', 'text-danger');
            }
        })
        .catch(() => setStatus('Erreur réseau', 'text-danger'));
    }

    function saveOrder() {
        clearTimeout(saveTimer);
        setStatus('…', 'text-muted');
        saveTimer = setTimeout(doSave, 600);
    }

    function initSortable(ul) {
        new Sortable(ul, {
            group:                { name: 'af-folders', pull: true, put: true },
            handle:               '.ks-af-handle',
            animation:            150,
            fallbackOnBody:       true,
            swapThreshold:        0.5,
            emptyInsertThreshold: 8,
            onEnd: saveOrder,
        });
    }

    document.querySelectorAll('.ks-af-list').forEach(initSortable);
})();
