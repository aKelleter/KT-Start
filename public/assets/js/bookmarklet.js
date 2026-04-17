const _bml = JSON.parse(document.getElementById('ks-bml-data')?.textContent || '{}');

// ── Sélecteur de dossier hiérarchique ────────────────────────────────────────
(function () {
    const listSel   = document.getElementById('bmlListId');
    const folderSel = document.getElementById('bmlFolderId');
    const wrap      = document.getElementById('bmlFolderWrap');
    if (!listSel || !folderSel || !wrap) return;

    const allFolders = _bml.folders ?? [];

    function buildOptions(parentId, listId, depth, out) {
        const prefix = '\u00a0\u00a0'.repeat(depth) + (depth > 0 ? '\u2514 ' : '');
        allFolders
            .filter(f => f.list_id == listId && (parentId === null ? f.parent_id === null : f.parent_id == parentId))
            .sort((a, b) => a.position - b.position || a.name.localeCompare(b.name))
            .forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = prefix + f.name;
                out.appendChild(opt);
                buildOptions(f.id, listId, depth + 1, out);
            });
    }

    function refreshFolders() {
        const listId = listSel.value;
        while (folderSel.options.length > 1) folderSel.remove(1);
        folderSel.value = '';

        if (!listId) {
            wrap.style.display = 'none';
            return;
        }
        const hasFolders = allFolders.some(f => f.list_id == listId);
        wrap.style.display = hasFolders ? '' : 'none';
        if (hasFolders) buildOptions(null, listId, 0, folderSel);
    }

    listSel.addEventListener('change', refreshFolders);
    refreshFolders();
})();

// ── Autocomplete tags multi-valeur ───────────────────────────────────────────
(function () {
    const input = document.getElementById('bmlTags');
    if (!input) return;
    const tags = _bml.tags ?? [];
    if (!tags.length) return;

    const dl = document.createElement('datalist');
    dl.id = '_dl_bmlTags';
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

// ── Mise à jour du champ host depuis l'URL ───────────────────────────────────
function updateHost(url) {
    try {
        document.getElementById('bmlHost').value = new URL(url).hostname;
    } catch (e) { /* URL incomplète */ }
}
