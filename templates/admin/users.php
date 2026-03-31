<?php

use App\Core\Auth;
use App\Core\View;

?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show mb-4 text-center" role="alert">
        <?= View::e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── En-tête ────────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="ks-admin-icon" style="background:rgba(2,136,209,.10);color:#0288D1">
        <i class="bi bi-people-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Utilisateurs</h1>
        <p class="text-muted small mb-0">Créer, modifier et supprimer les comptes utilisateurs.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Tableau utilisateurs ───────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fs-5 fw-semibold mb-0">
        <i class="bi bi-people me-2 text-muted"></i>Utilisateurs
        <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.72rem"><?= count($users) ?></span>
    </h2>
    <button class="btn btn-sm btn-primary"
            data-bs-toggle="modal" data-bs-target="#userModal" data-mode="add">
        <i class="bi bi-plus-lg me-1"></i>Ajouter
    </button>
</div>

<div class="ks-admin-card">
    <table class="table table-hover table-sm align-middle mb-0">
        <thead>
            <tr>
                <th>Email</th>
                <th style="width:90px">Rôle</th>
                <th style="width:110px">Créé le</th>
                <th style="width:70px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="4" class="text-muted text-center py-3">Aucun utilisateur.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
            <tr>
                <td class="fw-semibold">
                    <?= View::e($u['email']) ?>
                    <?php if ((int) $u['id'] === Auth::id()): ?>
                        <span class="text-muted fw-normal small ms-1">(vous)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                        <?= View::e($u['role']) ?>
                    </span>
                </td>
                <td class="text-muted small"><?= View::e(substr($u['created_at'], 0, 10)) ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#userModal"
                            data-mode="edit"
                            data-id="<?= $u['id'] ?>"
                            data-email="<?= View::e($u['email']) ?>"
                            data-role="<?= View::e($u['role']) ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── Modal Utilisateur ──────────────────────────────────────────────────── -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog ks-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="userForm">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="id" id="userId">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="userEmail"
                               placeholder="utilisateur@exemple.com" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Mot de passe
                            <span class="text-muted fw-normal" id="passwordHint" style="text-transform:none;letter-spacing:0"></span>
                        </label>
                        <input type="password" class="form-control" name="password" id="userPassword"
                               placeholder="8 caractères minimum" autocomplete="new-password">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Rôle</label>
                        <select class="form-select" name="role" id="userRole">
                            <option value="admin">admin</option>
                            <option value="user">user</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnUserDelete">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="userSubmitBtn">Ajouter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Formulaire de suppression caché ───────────────────────────────────── -->
<form method="post" action="?action=admin_user_delete" id="userDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="userDeleteId">
</form>

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script>
(function () {

    // ── Modal Utilisateur ──────────────────────────────────────────────────
    const userModal = document.getElementById('userModal');
    userModal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const mode = btn?.dataset.mode ?? 'add';
        const isEdit = mode === 'edit';

        document.getElementById('userModalTitle').textContent = isEdit ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur';
        document.getElementById('userSubmitBtn').textContent  = isEdit ? 'Enregistrer' : 'Ajouter';
        document.getElementById('userForm').action = isEdit ? '?action=admin_user_update' : '?action=admin_user_store';
        document.getElementById('btnUserDelete').classList.toggle('d-none', !isEdit);
        document.getElementById('passwordHint').textContent = isEdit ? '(laisser vide pour ne pas changer)' : '';

        if (isEdit) {
            document.getElementById('userId').value    = btn.dataset.id;
            document.getElementById('userEmail').value = btn.dataset.email;
            document.getElementById('userRole').value  = btn.dataset.role;
            document.getElementById('userDeleteId').value = btn.dataset.id;
        } else {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
        }
    });

    document.getElementById('btnUserDelete').addEventListener('click', function () {
        if (confirm('Supprimer cet utilisateur ?')) {
            document.getElementById('userDeleteForm').submit();
        }
    });

})();
</script>
