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
    <div class="ks-admin-icon" style="background:rgba(245,158,11,.10);color:#f59e0b">
        <i class="bi bi-folder-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Dossiers</h1>
        <p class="text-muted small mb-0">Organiser et hiérarchiser les dossiers par liste.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Sélecteur de liste ─────────────────────────────────────────────────── -->
<div class="ks-admin-card p-4 mb-4">
    <form method="get" action="" class="d-flex align-items-end gap-3 flex-wrap">
        <input type="hidden" name="action" value="admin_folders">
        <div class="flex-grow-1" style="min-width:200px;max-width:360px">
            <label class="form-label fw-semibold mb-1">Liste</label>
            <select name="list_id" class="form-select" onchange="this.form.submit()">
                <option value="">— Choisir une liste —</option>
                <?php foreach ($lists as $list): ?>
                <option value="<?= (int) $list['id'] ?>"
                    <?= $listId === (int) $list['id'] ? 'selected' : '' ?>>
                    <?= View::e($list['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($listId !== null): ?>
        <button type="button" class="btn btn-primary" id="btnAfCreateRoot">
            <i class="bi bi-folder-plus me-1"></i>Nouveau dossier
        </button>
        <?php endif; ?>
    </form>
</div>

<?php if ($listId === null): ?>
<div class="text-muted text-center py-4">
    <i class="bi bi-arrow-up text-muted me-1"></i>Sélectionnez une liste pour gérer ses dossiers.
</div>
<?php elseif (empty($folders)): ?>
<div class="ks-admin-card p-4 text-center text-muted">
    <i class="bi bi-folder2 fs-3 d-block mb-2 opacity-50"></i>
    Aucun dossier dans cette liste.
    <br><button type="button" class="btn btn-primary btn-sm mt-3" id="btnAfCreateRootEmpty">
        <i class="bi bi-folder-plus me-1"></i>Créer le premier dossier
    </button>
</div>
<?php else: ?>

<!-- ── Arbre des dossiers ─────────────────────────────────────────────────── -->
<div class="ks-admin-card p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <p class="text-muted small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Glissez-déposez pour réorganiser. Déposez un dossier <em>dans</em> un autre pour le nicher.
        </p>
        <span id="afSaveStatus" class="small text-muted ms-3 flex-shrink-0" style="min-width:120px;text-align:right"></span>
    </div>

    <?php
    $renderTree = function(int $parentKey, int $depth = 0) use (&$renderTree, $foldersByParent, $listId, $csrf): void {
        $children = $foldersByParent[$parentKey] ?? [];
        $parentAttr = $parentKey === 0 ? '' : $parentKey;
        ?>
        <ul class="ks-af-list list-unstyled mb-0<?= $depth === 0 ? ' ks-af-root' : '' ?>"
            data-parent-id="<?= $parentAttr ?>"
            data-list-id="<?= (int) $listId ?>">
        <?php foreach ($children as $folder): ?>
            <?php $fid = (int) $folder['id']; ?>
            <li class="ks-af-node" data-id="<?= $fid ?>">
                <div class="ks-af-row d-flex align-items-center gap-2 py-2 px-2 rounded">
                    <span class="ks-af-handle text-muted flex-shrink-0" title="Réorganiser">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <i class="bi bi-folder2-open text-warning flex-shrink-0"></i>
                    <span class="fw-semibold flex-grow-1 text-truncate"><?= View::e($folder['name']) ?></span>
                    <span class="badge text-bg-secondary fw-normal flex-shrink-0 d-none d-sm-inline"
                          style="font-size:.68rem;max-width:120px;overflow:hidden;text-overflow:ellipsis"
                          title="<?= View::e($folder['user_email'] ?? '') ?>">
                        <?= View::e($folder['user_email'] ?? '') ?>
                    </span>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 ks-af-create-child"
                                data-id="<?= $fid ?>"
                                data-name="<?= View::e($folder['name']) ?>"
                                title="Nouveau sous-dossier">
                            <i class="bi bi-folder-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 ks-af-rename"
                                data-id="<?= $fid ?>"
                                data-name="<?= View::e($folder['name']) ?>"
                                title="Renommer">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 ks-af-delete"
                                data-id="<?= $fid ?>"
                                data-name="<?= View::e($folder['name']) ?>"
                                title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <?php $renderTree($fid, $depth + 1); ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php
    };
    $renderTree(0, 0);
    ?>
</div>

<?php endif; ?>

<!-- ── Formulaires cachés (uniquement si une liste est sélectionnée) ─────── -->
<?php if ($listId !== null): ?>
<form method="post" action="?action=admin_folder_store" id="afCreateForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="parent_id" id="afCreateParentId" value="">
    <input type="hidden" name="name" id="afCreateName" value="">
</form>

<form method="post" action="?action=admin_folder_rename" id="afRenameForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="id" id="afRenameId" value="">
    <input type="hidden" name="name" id="afRenameName" value="">
</form>

<form method="post" action="?action=admin_folder_delete" id="afDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="id" id="afDeleteId" value="">
</form>
<?php endif; ?>

<!-- ── Modals ─────────────────────────────────────────────────────────────── -->
<div class="modal fade ks-modal" id="afCreateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="afCreateModalTitle">Créer un dossier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small" id="afCreateModalHint"></div>
                <label class="form-label">Nom du dossier</label>
                <input type="text" class="form-control" id="afCreateInput" maxlength="120" placeholder="Nom du dossier">
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="afCreateConfirm">Créer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade ks-modal" id="afRenameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Renommer le dossier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nouveau nom</label>
                <input type="text" class="form-control" id="afRenameInput" maxlength="120" placeholder="Nouveau nom">
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="afRenameConfirm">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade ks-modal" id="afDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-trash text-danger me-2"></i>Supprimer le dossier ?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-1" id="afDeleteModalName"></p>
                <p class="text-muted small mb-0">Les sous-dossiers et favoris seront remontés d'un niveau.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger btn-sm" id="afDeleteConfirm">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<?php if ($listId !== null): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script type="application/json" id="ks-af-data">
<?= json_encode(['listId' => (int) $listId], JSON_UNESCAPED_UNICODE) ?>
</script>
<script src="<?= View::asset('js/admin/folders.js') ?>"></script>

<?php endif; ?>
