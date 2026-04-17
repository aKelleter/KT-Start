(function () {

    const userModal = document.getElementById('userModal');
    userModal.addEventListener('show.bs.modal', function (e) {
        const btn    = e.relatedTarget;
        const mode   = btn?.dataset.mode ?? 'add';
        const isEdit = mode === 'edit';

        document.getElementById('userModalTitle').textContent = isEdit ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur';
        document.getElementById('userSubmitBtn').textContent  = isEdit ? 'Enregistrer' : 'Ajouter';
        document.getElementById('userForm').action = isEdit ? '?action=admin_user_update' : '?action=admin_user_store';
        document.getElementById('btnUserDelete').classList.toggle('d-none', !isEdit);
        document.getElementById('passwordHint').textContent = isEdit ? '(laisser vide pour ne pas changer)' : '';

        const pwdConfirm = document.getElementById('userPasswordConfirm');
        pwdConfirm.value = '';
        pwdConfirm.classList.remove('is-invalid');

        if (isEdit) {
            document.getElementById('userId').value       = btn.dataset.id;
            document.getElementById('userEmail').value    = btn.dataset.email;
            document.getElementById('userRole').value     = btn.dataset.role;
            document.getElementById('userDeleteId').value = btn.dataset.id;
        } else {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
        }
    });

    document.getElementById('userPassword').addEventListener('input', function () {
        const confirm = document.getElementById('userPasswordConfirm');
        if (confirm.classList.contains('is-invalid')) {
            confirm.classList.remove('is-invalid');
        }
    });

    document.getElementById('userForm').addEventListener('submit', function (e) {
        const pwd     = document.getElementById('userPassword').value;
        const confirm = document.getElementById('userPasswordConfirm');
        if (pwd && pwd !== confirm.value) {
            e.preventDefault();
            confirm.classList.add('is-invalid');
            confirm.focus();
        }
    });

    document.getElementById('btnUserDelete').addEventListener('click', function () {
        if (confirm('Supprimer cet utilisateur ?')) {
            document.getElementById('userDeleteForm').submit();
        }
    });

})();
