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
<div class="d-flex align-items-center gap-3 mb-5">
    <div class="ks-admin-icon"><i class="bi bi-gear-fill"></i></div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Administration</h1>
        <p class="text-muted small mb-0">Gestion des utilisateurs et des listes</p>
    </div>
    <a href="?action=bookmarks" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<!-- ── Utilisateurs ───────────────────────────────────────────────────────── -->
<section class="ks-admin-section mb-5">
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
</section>

<!-- ── Listes ─────────────────────────────────────────────────────────────── -->
<section class="ks-admin-section">
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
</section>

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

<!-- ── Paramètres ─────────────────────────────────────────────────────────── -->
<section class="ks-admin-section mt-5" id="parametres">
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="fs-5 fw-semibold mb-0">
            <i class="bi bi-sliders me-2 text-muted"></i>Paramètres
        </h2>
    </div>

    <div class="ks-admin-card p-4">
        <form method="post" action="?action=admin_setting_update">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">
                        Favoris par page
                        <?php if (!empty($envPerPage)): ?>
                            <span class="text-muted fw-normal small ms-1">
                                (.env : <?= (int) $envPerPage ?>)
                            </span>
                        <?php endif; ?>
                    </label>
                    <input type="number" class="form-control" name="bookmarks_per_page"
                           min="1" max="500"
                           value="<?= (int) ($settings['bookmarks_per_page'] ?? $envPerPage ?? 24) ?>"
                           required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
            <div class="form-text mt-2">
                La valeur en base de données prend le dessus sur <code>.env</code>.
            </div>

        </form>
    </div>
</section>

<!-- ── Sauvegarde ─────────────────────────────────────────────────────────── -->
<section class="ks-admin-section mt-5" id="sauvegarde">
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="fs-5 fw-semibold mb-0">
            <i class="bi bi-archive me-2 text-muted"></i>Sauvegarde
        </h2>
    </div>

    <div class="ks-admin-card p-4">

        <!-- Export backup complet -->
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
                <i class="bi bi-database-down"></i>
            </div>
            <div class="flex-grow-1">
                <p class="fw-semibold mb-1" style="font-size:.95rem">Backup complet</p>
                <p class="text-muted small mb-0">
                    Télécharge un backup JSON complet : utilisateurs, paramètres, listes et favoris (tous utilisateurs).
                </p>
            </div>
            <a href="?action=admin_export_full" class="btn btn-sm btn-primary ms-auto flex-shrink-0">
                <i class="bi bi-database-down me-1"></i>Backup
            </a>
        </div>

        <!-- Export favoris uniquement -->
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
                <i class="bi bi-download"></i>
            </div>
            <div class="flex-grow-1">
                <p class="fw-semibold mb-1" style="font-size:.95rem">Exporter les favoris</p>
                <p class="text-muted small mb-0">
                    Télécharge uniquement vos favoris et listes (format portable entre instances).
                </p>
            </div>
            <a href="?action=admin_export" class="btn btn-sm btn-outline-secondary ms-auto flex-shrink-0">
                <i class="bi bi-download me-1"></i>Exporter
            </a>
        </div>

        <hr class="my-3" style="border-color:rgba(0,0,0,.06)">

        <!-- Import -->
        <form method="post" action="?action=admin_import" enctype="multipart/form-data" id="importForm">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <input type="hidden" name="full_restore" id="fullRestoreInput" value="0">

            <div class="d-flex align-items-start gap-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(99,102,241,.10);color:#6366f1">
                    <i class="bi bi-upload"></i>
                </div>
                <div class="flex-grow-1">
                    <p class="fw-semibold mb-1" style="font-size:.95rem">Importer des favoris</p>
                    <p class="text-muted small mb-2">
                        Charge un fichier JSON issu d'un export KT-Start.<br>
                        <strong>Export favoris (v1) :</strong> vos favoris et listes existants sont remplacés.<br>
                        <strong>Backup complet (v2) :</strong> utilisez "Restauration complète" pour écraser toutes les données.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input type="file" class="form-control form-control-sm" name="import_file"
                               accept=".json,application/json" required style="max-width:280px">
                        <button type="submit" class="btn btn-sm btn-primary" id="btnImport">
                            <i class="bi bi-upload me-1"></i>Importer
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnFullRestore">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restauration complète
                        </button>
                    </div>
                </div>
            </div>

            <div id="fullRestoreWarning" class="alert alert-danger py-2 px-3 mb-0 mt-3 small d-none" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Attention :</strong> toutes les données existantes (favoris, listes, utilisateurs, paramètres)
                seront <strong>définitivement effacées</strong> avant l'import.
                Vous serez déconnecté à l'issue de l'opération.
            </div>
        </form>

        <?php if (!empty($importResult)): ?>
        <div class="ks-migration-log mt-4">
            <?php if ($importResult['imported'] > 0): ?>
            <div class="ks-migration-line">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <?= $importResult['imported'] ?> favori<?= $importResult['imported'] > 1 ? 's' : '' ?> importé<?= $importResult['imported'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($importResult['lists_created'] > 0): ?>
            <div class="ks-migration-line">
                <i class="bi bi-plus-circle-fill text-primary me-2"></i>
                <?= $importResult['lists_created'] ?> liste<?= $importResult['lists_created'] > 1 ? 's' : '' ?> créée<?= $importResult['lists_created'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($importResult['users_created'])): ?>
            <div class="ks-migration-line">
                <i class="bi bi-person-check-fill text-success me-2"></i>
                <?= $importResult['users_created'] ?> utilisateur<?= $importResult['users_created'] > 1 ? 's' : '' ?> créé<?= $importResult['users_created'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($importResult['users_skipped'])): ?>
            <div class="ks-migration-line muted">
                <i class="bi bi-person-dash text-muted me-2"></i>
                <?= $importResult['users_skipped'] ?> utilisateur<?= $importResult['users_skipped'] > 1 ? 's' : '' ?> ignoré<?= $importResult['users_skipped'] > 1 ? 's' : '' ?> (email déjà existant)
            </div>
            <?php endif; ?>
            <?php if (!empty($importResult['settings_updated'])): ?>
            <div class="ks-migration-line">
                <i class="bi bi-sliders text-primary me-2"></i>
                <?= $importResult['settings_updated'] ?> paramètre<?= $importResult['settings_updated'] > 1 ? 's' : '' ?> restauré<?= $importResult['settings_updated'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($importResult['skipped'] > 0): ?>
            <div class="ks-migration-line">
                <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                <?= $importResult['skipped'] ?> favori<?= $importResult['skipped'] > 1 ? 's' : '' ?> ignoré<?= $importResult['skipped'] > 1 ? 's' : '' ?> (invalides)
            </div>
            <?php endif; ?>
            <?php foreach ($importResult['errors'] as $err): ?>
            <div class="ks-migration-line">
                <i class="bi bi-x-circle-fill text-danger me-2"></i><?= View::e($err) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ── Maintenance ────────────────────────────────────────────────────────── -->
<section class="ks-admin-section mt-5" id="maintenance">
    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="fs-5 fw-semibold mb-0">
            <i class="bi bi-wrench-adjustable me-2 text-muted"></i>Maintenance
        </h2>
    </div>

    <div class="ks-admin-card p-4">
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="ks-admin-icon flex-shrink-0" style="background:rgba(99,102,241,.10);color:#6366f1">
                <i class="bi bi-database-gear"></i>
            </div>
            <div>
                <p class="fw-semibold mb-1" style="font-size:.95rem">Mise à jour de la base de données</p>
                <p class="text-muted small mb-0">
                    Exécute les migrations idempotentes : crée les tables manquantes, ajoute les colonnes absentes.
                    Sans effet sur les données existantes.
                </p>
            </div>
            <form method="post" action="?action=admin_run_migration" class="ms-auto flex-shrink-0">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-play-fill me-1"></i>Lancer
                </button>
            </form>
        </div>

        <?php if (!empty($migrationLog)): ?>
        <div class="ks-migration-log">
            <?php foreach ($migrationLog as $entry): ?>
                <?php
                    $icon  = match($entry['status']) {
                        'created' => '<i class="bi bi-plus-circle-fill text-primary me-2"></i>',
                        'added'   => '<i class="bi bi-plus-square-fill text-success me-2"></i>',
                        'error'   => '<i class="bi bi-x-circle-fill text-danger me-2"></i>',
                        default   => '<i class="bi bi-check-circle-fill text-muted me-2"></i>',
                    };
                ?>
                <div class="ks-migration-line <?= $entry['status'] === 'ok' ? 'muted' : '' ?>">
                    <?= $icon ?><?= View::e($entry['message']) ?>
                </div>
            <?php endforeach; ?>
            <div class="ks-migration-line mt-2 pt-2 border-top fw-semibold" style="font-size:.8rem">
                <i class="bi bi-check2-all me-2 text-success"></i>Migration terminée.
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Formulaires de suppression ────────────────────────────────────────── -->
<form method="post" action="?action=admin_user_delete" id="userDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="userDeleteId">
</form>

<form method="post" action="?action=admin_list_delete" id="listDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="listDeleteId">
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

    // ── Modal Liste ────────────────────────────────────────────────────────
    const listModal = document.getElementById('listModal');
    listModal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const mode = btn?.dataset.mode ?? 'add';
        const isEdit = mode === 'edit';

        document.getElementById('listModalTitle').textContent = isEdit ? 'Renommer la liste' : 'Ajouter une liste';
        document.getElementById('listSubmitBtn').textContent  = isEdit ? 'Renommer' : 'Ajouter';
        document.getElementById('listForm').action = isEdit ? '?action=admin_list_rename' : '?action=admin_list_store';
        document.getElementById('btnListDelete').classList.toggle('d-none', !isEdit);

        if (isEdit) {
            document.getElementById('listId').value   = btn.dataset.id;
            document.getElementById('listName').value = btn.dataset.name;
            document.getElementById('listDeleteId').value = btn.dataset.id;
        } else {
            document.getElementById('listForm').reset();
            document.getElementById('listId').value = '';
        }
    });

    document.getElementById('btnListDelete').addEventListener('click', function () {
        if (confirm('Supprimer cette liste ? Les favoris associés ne seront pas supprimés.')) {
            document.getElementById('listDeleteForm').submit();
        }
    });

    // ── Restauration complète ────────────────────────────────────────────
    const btnFullRestore  = document.getElementById('btnFullRestore');
    const fullRestoreInput   = document.getElementById('fullRestoreInput');
    const fullRestoreWarning = document.getElementById('fullRestoreWarning');

    if (btnFullRestore) {
        btnFullRestore.addEventListener('click', function () {
            if (fullRestoreInput.value === '1') {
                // Déjà en mode restauration → soumettre avec confirmation
                if (confirm('Confirmer la restauration complète ?\n\nToutes les données actuelles seront effacées et remplacées par le contenu du fichier. Cette action est irréversible.')) {
                    document.getElementById('importForm').submit();
                }
            } else {
                // Passer en mode restauration complète
                fullRestoreInput.value = '1';
                fullRestoreWarning.classList.remove('d-none');
                btnFullRestore.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Confirmer la restauration';
                btnFullRestore.classList.replace('btn-outline-danger', 'btn-danger');
                document.getElementById('btnImport').classList.add('d-none');
            }
        });
    }

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
