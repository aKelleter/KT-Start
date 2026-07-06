<?php

use App\Core\View;

?>

<div id="listFlashContainer">
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show mb-4 text-center" role="alert">
        <?= View::e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
</div>

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
        <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.72rem" id="listCountBadge"><?= count($lists) ?></span>
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
        <?php require BASE_PATH . '/templates/admin/_lists_rows.php'; ?>
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
<script src="<?= View::asset('js/admin/lists.js') ?>"></script>
