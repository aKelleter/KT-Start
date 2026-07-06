(function () {

    const listModalEl       = document.getElementById('listModal');
    const listDeleteModalEl = document.getElementById('listDeleteConfirmModal');
    const tableBody         = document.getElementById('listTableBody');
    const countBadge        = document.getElementById('listCountBadge');
    const flashContainer    = document.getElementById('listFlashContainer');
    let   pendingDelete     = false;

    function showFlash(type, message) {
        flashContainer.innerHTML =
            '<div class="alert alert-' + type + ' alert-dismissible fade show mb-4 text-center" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';

        const alertEl = flashContainer.querySelector('.alert');
        if (alertEl && window.ksAutoDismissFlash) {
            window.ksAutoDismissFlash(alertEl);
        }
    }

    async function submitAjax(form) {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        const data = await response.json();

        if (data.html !== undefined) {
            tableBody.innerHTML = data.html;
        }
        if (data.count !== undefined) {
            countBadge.textContent = data.count;
        }
        showFlash(data.ok ? 'success' : 'danger', data.message);

        return data;
    }

    listModalEl.addEventListener('show.bs.modal', function (e) {
        const btn    = e.relatedTarget;
        const mode   = btn?.dataset.mode ?? 'add';
        const isEdit = mode === 'edit';

        document.getElementById('listModalTitle').textContent = isEdit ? 'Renommer la liste' : 'Ajouter une liste';
        document.getElementById('listSubmitBtn').textContent  = isEdit ? 'Renommer' : 'Ajouter';
        document.getElementById('listForm').action = isEdit ? '?action=admin_list_rename' : '?action=admin_list_store';
        document.getElementById('btnListDelete').classList.toggle('d-none', !isEdit);

        if (isEdit) {
            document.getElementById('listId').value                      = btn.dataset.id;
            document.getElementById('listName').value                    = btn.dataset.name;
            document.getElementById('listDeleteId').value                = btn.dataset.id;
            document.getElementById('listDeleteConfirmName').textContent = btn.dataset.name;
        } else {
            document.getElementById('listForm').reset();
            document.getElementById('listId').value = '';
        }
        pendingDelete = false;
    });

    document.getElementById('listForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const data = await submitAjax(this);
        if (data.ok) {
            bootstrap.Modal.getOrCreateInstance(listModalEl).hide();
        }
    });

    document.getElementById('btnListDelete').addEventListener('click', function () {
        pendingDelete = true;
        bootstrap.Modal.getOrCreateInstance(listModalEl).hide();
    });

    listModalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingDelete) {
            pendingDelete = false;
            bootstrap.Modal.getOrCreateInstance(listDeleteModalEl).show();
        }
    });

    document.getElementById('btnListDeleteCancel').addEventListener('click', function () {
        bootstrap.Modal.getOrCreateInstance(listDeleteModalEl).hide();
    });

    document.getElementById('btnListDeleteConfirm').addEventListener('click', async function () {
        await submitAjax(document.getElementById('listDeleteForm'));
        bootstrap.Modal.getOrCreateInstance(listDeleteModalEl).hide();
    });

    // Formulaires "définir par défaut" : régénérés à chaque rendu du tableau,
    // on écoute donc sur le conteneur stable plutôt que sur chaque formulaire.
    tableBody.addEventListener('submit', async function (e) {
        const form = e.target.closest('form');
        if (!form || !form.action.includes('admin_list_set_default')) {
            return;
        }
        e.preventDefault();
        await submitAjax(form);
    });

    document.getElementById('listFilter').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#listTableBody tr').forEach(tr => {
            const name = tr.querySelector('td')?.textContent.toLowerCase() ?? '';
            tr.style.display = name.includes(q) ? '' : 'none';
        });
    });

})();
