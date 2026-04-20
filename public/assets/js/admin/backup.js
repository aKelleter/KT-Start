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

    // ── Spinner import favoris v1 ────────────────────────────────────────────
    const importForm = document.getElementById('importForm');
    if (importForm) {
        importForm.addEventListener('submit', function () {
            const spinner = document.getElementById('btnImportSpinner');
            const icon    = document.getElementById('btnImportIcon');
            const label   = document.getElementById('btnImportLabel');
            const btn     = document.getElementById('btnImport');
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            label.textContent = 'Import en cours…';
            btn.disabled = true;
        });
    }

    // ── Restauration complète — modal iOS ────────────────────────────────────
    const btnRestore      = document.getElementById('btnRestore');
    const restoreFileInput = document.getElementById('restoreFileInput');
    const btnConfirm      = document.getElementById('btnConfirmRestore');
    const restoreForm     = document.getElementById('restoreForm');

    // Bloquer l'ouverture de la modale si aucun fichier sélectionné
    if (btnRestore) {
        btnRestore.addEventListener('click', function (e) {
            if (!restoreFileInput || !restoreFileInput.files.length) {
                e.stopImmediatePropagation();
                restoreFileInput.classList.add('is-invalid');
                restoreFileInput.focus();
                return;
            }
            restoreFileInput.classList.remove('is-invalid');
        });

        restoreFileInput.addEventListener('change', function () {
            restoreFileInput.classList.remove('is-invalid');
        });
    }

    if (btnConfirm && restoreForm) {
        btnConfirm.addEventListener('click', function () {
            const spinner = document.getElementById('btnConfirmRestoreSpinner');
            btnConfirm.disabled = true;
            spinner.classList.remove('d-none');
            restoreForm.submit();
        });
    }

})();
