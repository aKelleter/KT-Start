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
    <div class="ks-admin-icon" style="background:rgba(20,184,166,.10);color:#14b8a6">
        <i class="bi bi-tags-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Tags</h1>
        <p class="text-muted small mb-0">Renommer et supprimer les tags de tous les favoris.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Tableau tags ───────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <h2 class="fs-5 fw-semibold mb-0 me-auto">
        <i class="bi bi-tags me-2 text-muted"></i>Tags
        <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.72rem"><?= count($tags) ?></span>
    </h2>
    <?php $uniqueCount = count(array_filter($tags, fn($c) => $c === 1)); ?>
    <?php if ($uniqueCount > 0): ?>
    <form method="post" action="?action=admin_tag_delete_unique" id="deleteUniqueForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <button type="button" class="btn btn-sm" id="btnDeleteUnique"
                style="background:#fef9c3;color:#4b5563;border-color:#fde68a"
                title="Supprimer les <?= $uniqueCount ?> tags utilisés par un seul favori">
            <i class="bi bi-stars me-1"></i>Nettoyer (<?= $uniqueCount ?> unique<?= $uniqueCount > 1 ? 's' : '' ?>)
        </button>
    </form>
    <?php endif; ?>
    <input type="search" id="tagFilter" class="form-control form-control-sm"
           placeholder="Rechercher un tag…" autocomplete="off" style="width:200px">
</div>

<?php if (empty($tags)): ?>
<div class="ks-admin-card p-4 text-center text-muted">
    <i class="bi bi-tags fs-3 d-block mb-2 opacity-25"></i>
    Aucun tag trouvé. Ajoutez des tags à vos favoris pour les gérer ici.
</div>
<?php else: ?>
<div class="ks-admin-card" style="max-height:520px;overflow-y:auto">
    <table class="table table-hover table-sm align-middle mb-0">
        <thead>
            <tr>
                <th>Tag</th>
                <th style="width:110px">Favoris</th>
                <th style="width:80px"></th>
            </tr>
        </thead>
        <tbody id="tagTableBody">
        <?php foreach ($tags as $tag => $count): ?>
            <tr data-tag="<?= View::e(strtolower($tag)) ?>">
                <td class="fw-semibold"><?= View::e($tag) ?></td>
                <td class="text-muted small">
                    <?= $count ?> favori<?= $count > 1 ? 's' : '' ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary btn-tag-edit"
                            data-tag="<?= View::e($tag) ?>"
                            data-count="<?= $count ?>"
                            title="Renommer ce tag">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ── Modal Tag ──────────────────────────────────────────────────────────── -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog ks-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="?action=admin_tag_rename" id="tagRenameForm">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="old" id="tagOld">
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="tagModalInfo"></p>
                    <div class="mb-1">
                        <label class="form-label">Nouveau nom</label>
                        <input type="text" class="form-control" name="new" id="tagNew"
                               placeholder="Nouveau nom du tag" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnTagDelete">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Renommer</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Formulaire de suppression caché ───────────────────────────────────── -->
<form method="post" action="?action=admin_tag_delete" id="tagDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="tag" id="tagDeleteName">
</form>

<!-- ── Modal de confirmation (style iOS) ─────────────────────────────────── -->
<div class="modal fade" id="tagConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:320px">
        <div class="modal-content rounded-4 overflow-hidden border-0 shadow">
            <div class="modal-body text-center px-4 pt-4 pb-3">
                <p class="fw-semibold mb-1" id="tagConfirmTitle"></p>
                <p class="text-muted small mb-0" id="tagConfirmSubtitle"></p>
            </div>
            <div class="border-top d-flex" style="height:44px">
                <button type="button" class="btn btn-link flex-fill text-secondary fw-normal border-end rounded-0"
                        data-bs-dismiss="modal" id="tagConfirmCancel">Annuler</button>
                <button type="button" class="btn btn-link flex-fill text-danger fw-semibold rounded-0"
                        id="tagConfirmOk">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script src="<?= View::asset('js/admin/tags.js') ?>"></script>
