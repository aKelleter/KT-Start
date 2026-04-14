<?php

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
    <div class="ks-admin-icon" style="background:rgba(99,102,241,.10);color:#6366f1">
        <i class="bi bi-collection-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Listes</h1>
        <p class="text-muted small mb-0">Organiser vos favoris en listes thématiques.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Tableau listes ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fs-5 fw-semibold mb-0">
        <i class="bi bi-folder me-2 text-muted"></i>Listes
        <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.72rem"><?= count($lists) ?></span>
    </h2>
    <div class="d-flex align-items-center gap-2">
        <input type="search" id="listFilter" class="form-control form-control-sm"
               placeholder="Rechercher une liste…" autocomplete="off" style="width:200px">
        <button class="btn btn-sm btn-primary"
                data-bs-toggle="modal" data-bs-target="#listModal" data-mode="add">
            <i class="bi bi-plus-lg me-1"></i>Ajouter
        </button>
    </div>
</div>

<div class="ks-admin-card" style="max-height:420px;overflow-y:auto">
    <table class="table table-hover table-sm align-middle mb-0">
        <thead>
            <tr>
                <th>Nom</th>
                <th style="width:100px">Favoris</th>
                <th style="width:110px">Créée le</th>
                <th style="width:100px"></th>
            </tr>
        </thead>
        <tbody id="listTableBody">
        <?php if (empty($lists)): ?>
            <tr><td colspan="4" class="text-muted text-center py-3">Aucune liste.</td></tr>
        <?php endif; ?>
        <?php foreach ($lists as $l): ?>
            <?php $isDefault = (int) $l['id'] === $defaultListId; ?>
            <tr>
                <td class="fw-semibold">
                    <?= View::e($l['name']) ?>
                    <?php if ($isDefault): ?>
                        <i class="bi bi-star-fill text-warning ms-1" style="font-size:.8rem" title="Liste par défaut"></i>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= (int) $l['bookmark_count'] ?> favori<?= $l['bookmark_count'] > 1 ? 's' : '' ?></td>
                <td class="text-muted small"><?= View::e(substr($l['created_at'], 0, 10)) ?></td>
                <td class="d-flex gap-1">
                    <form method="post" action="?action=admin_list_set_default">
                        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button type="submit"
                                class="btn btn-sm <?= $isDefault ? 'btn-warning' : 'btn-outline-secondary' ?>"
                                title="<?= $isDefault ? 'Retirer comme liste par défaut' : 'Définir comme liste par défaut' ?>">
                            <i class="bi bi-star<?= $isDefault ? '-fill' : '' ?>"></i>
                        </button>
                    </form>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#listModal"
                            data-mode="edit"
                            data-id="<?= $l['id'] ?>"
                            data-name="<?= View::e($l['name']) ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── Modal Liste ────────────────────────────────────────────────────────── -->
<div class="modal fade" id="listModal" tabindex="-1">
    <div class="modal-dialog ks-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="listModalTitle">Ajouter une liste</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="listForm">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="id" id="listId">
                <div class="modal-body">
                    <div class="mb-1">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="name" id="listName"
                               placeholder="Nom de la liste" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnListDelete">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="listSubmitBtn">Ajouter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Formulaire de suppression caché ───────────────────────────────────── -->
<form method="post" action="?action=admin_list_delete" id="listDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="listDeleteId">
</form>

<!-- ── Modal de confirmation suppression (style iOS) ─────────────────────── -->
<div class="modal fade" id="listDeleteConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:320px">
        <div class="modal-content rounded-4 overflow-hidden border-0 shadow">
            <div class="modal-body text-center px-4 pt-4 pb-3">
                <p class="fw-semibold mb-1">Supprimer cette liste ?</p>
                <p class="text-muted small mb-0">« <span id="listDeleteConfirmName"></span> »<br>Les favoris associés ne seront pas supprimés.</p>
            </div>
            <div class="d-flex border-top" style="min-height:44px">
                <button type="button" id="btnListDeleteCancel"
                        class="btn btn-link flex-fill text-secondary text-decoration-none fw-normal rounded-0"
                        style="border-right:1px solid var(--bs-border-color)">
                    Annuler
                </button>
                <button type="button" id="btnListDeleteConfirm"
                        class="btn btn-link flex-fill text-danger text-decoration-none fw-semibold rounded-0">
                    Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script>
(function () {

    // ── Modal Liste ────────────────────────────────────────────────────────
    // Références aux éléments DOM uniquement (Bootstrap est chargé après le template)
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

    // Clic "Supprimer" : lever le flag, puis fermer le modal d'édition
    document.getElementById('btnListDelete').addEventListener('click', function () {
        pendingDelete = true;
        bootstrap.Modal.getOrCreateInstance(listModalEl).hide();
    });

    // Quand le modal d'édition est totalement fermé : ouvrir la confirmation si demandé
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

    // ── Filtre liste ──────────────────────────────────────────────────────
    document.getElementById('listFilter').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#listTableBody tr').forEach(tr => {
            const name = tr.querySelector('td')?.textContent.toLowerCase() ?? '';
            tr.style.display = name.includes(q) ? '' : 'none';
        });
    });

})();
</script>
