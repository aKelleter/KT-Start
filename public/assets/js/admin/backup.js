(function () {

    // ── Import HTML — toggle liste existante / nouvelle ──────────────────────
    const radios       = document.querySelectorAll('input[name="html_list_choice"]');
    const listSelect   = document.getElementById('htmlListSelect');
    const newListInput = document.getElementById('htmlNewListName');

    function syncHtmlListFields() {
        const isNew = document.getElementById('htmlListNew').checked;
        listSelect.disabled   = isNew;
        newListInput.disabled = !isNew;
        if (isNew) newListInput.focus();
    }

    radios.forEach(r => r.addEventListener('change', syncHtmlListFields));

    const importHtmlForm = document.getElementById('importHtmlForm');
    if (importHtmlForm) {
        importHtmlForm.addEventListener('submit', function () {
            const spinner = document.getElementById('btnImportHtmlSpinner');
            const icon    = document.getElementById('btnImportHtmlIcon');
            const btn     = document.getElementById('btnImportHtml');
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;
        });
    }

    // ── Restauration complète ────────────────────────────────────────────────
    const btnFullRestore     = document.getElementById('btnFullRestore');
    const fullRestoreInput   = document.getElementById('fullRestoreInput');
    const fullRestoreWarning = document.getElementById('fullRestoreWarning');
    const importForm         = document.getElementById('importForm');

    if (btnFullRestore) {
        btnFullRestore.addEventListener('click', function () {
            if (fullRestoreInput.value === '1') {
                if (confirm('Confirmer la restauration complète ?\n\nToutes les données actuelles seront effacées et remplacées par le contenu du fichier. Cette action est irréversible.')) {
                    showImportSpinner(true);
                    importForm.submit();
                }
            } else {
                fullRestoreInput.value = '1';
                fullRestoreWarning.classList.remove('d-none');
                btnFullRestore.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Confirmer la restauration';
                btnFullRestore.classList.replace('btn-outline-danger', 'btn-danger');
                document.getElementById('btnImport').classList.add('d-none');
            }
        });
    }

    // ── Spinner import JSON ──────────────────────────────────────────────────
    function showImportSpinner(forRestore) {
        const spinner = document.getElementById('btnImportSpinner');
        const icon    = document.getElementById('btnImportIcon');
        const label   = document.getElementById('btnImportLabel');
        const btn     = document.getElementById('btnImport');
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        label.textContent = forRestore ? 'Restauration…' : 'Import en cours…';
        btn.disabled = true;
        btnFullRestore.disabled = true;
    }

    if (importForm) {
        importForm.addEventListener('submit', function () {
            showImportSpinner(fullRestoreInput.value === '1');
        });
    }

})();
