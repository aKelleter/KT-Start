(function () {

    const listModalEl       = document.getElementById('listModal');
    const listDeleteModalEl = document.getElementById('listDeleteConfirmModal');
    let   pendingDelete     = false;

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

    document.getElementById('btnListDeleteConfirm').addEventListener('click', function () {
        document.getElementById('listDeleteForm').submit();
    });

    document.getElementById('listFilter').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#listTableBody tr').forEach(tr => {
            const name = tr.querySelector('td')?.textContent.toLowerCase() ?? '';
            tr.style.display = name.includes(q) ? '' : 'none';
        });
    });

})();
