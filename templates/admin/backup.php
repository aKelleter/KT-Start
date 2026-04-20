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
        <i class="bi bi-archive-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Sauvegarde</h1>
        <p class="text-muted small mb-0">Exporter et importer vos données.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Section Exporter ───────────────────────────────────────────────────── -->
<p class="ks-section-label">Exporter</p>

<div class="ks-admin-card overflow-hidden p-0 mb-4">

    <div class="ks-backup-row d-flex align-items-center gap-3 px-4 py-3">
        <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
            <i class="bi bi-database-down"></i>
        </div>
        <div class="flex-grow-1">
            <p class="fw-semibold mb-0" style="font-size:.925rem">Backup complet <span class="badge text-bg-primary ms-1" style="font-size:.7rem;vertical-align:middle">v2</span></p>
            <p class="text-muted mb-0" style="font-size:.82rem">Utilisateurs, paramètres, listes et favoris — tous les comptes. À utiliser pour une restauration complète.</p>
        </div>
        <a href="?action=admin_export_full" class="btn btn-sm btn-primary flex-shrink-0">
            <i class="bi bi-database-down me-1"></i>Backup
        </a>
    </div>

    <div class="ks-inset-divider"></div>

    <div class="ks-backup-row d-flex align-items-center gap-3 px-4 py-3">
        <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
            <i class="bi bi-download"></i>
        </div>
        <div class="flex-grow-1">
            <p class="fw-semibold mb-0" style="font-size:.925rem">Favoris uniquement <span class="badge text-bg-secondary ms-1" style="font-size:.7rem;vertical-align:middle">v1</span></p>
            <p class="text-muted mb-0" style="font-size:.82rem">Vos favoris et listes au format JSON portable. Ne contient pas les comptes utilisateurs.</p>
        </div>
        <a href="?action=admin_export" class="btn btn-sm btn-outline-secondary flex-shrink-0">
            <i class="bi bi-download me-1"></i>Exporter
        </a>
    </div>

</div>

<!-- ── Section Importer — navigateur ─────────────────────────────────────── -->
<p class="ks-section-label">Importer depuis un navigateur</p>

<div class="ks-admin-card overflow-hidden p-0 mb-4">
    <form method="post" action="?action=admin_import_html" enctype="multipart/form-data" id="importHtmlForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

        <div class="ks-backup-row px-4 py-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(34,197,94,.10);color:#16a34a">
                    <i class="bi bi-filetype-html"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-0" style="font-size:.925rem">Fichier navigateur</p>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        HTML Netscape ou JSON Firefox — compatible Chrome, Safari, Edge. Les dossiers sont recréés dans la liste cible.
                    </p>
                </div>
            </div>

            <div class="ks-inset-divider"></div>
            
            <div class="ps-1 mt-3 mb-2">
                <input type="file" class="form-control form-control-sm" name="import_html_file"
                       accept=".html,.htm,.json,text/html,application/json" required style="max-width:300px">
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3 ps-1">
                <span class="small text-muted flex-shrink-0">Ajouter à</span>
                <div class="d-flex align-items-center gap-2">
                    <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="html_list_choice"
                           id="htmlListExisting" value="existing" checked>
                    <label class="form-check-label small" for="htmlListExisting">Liste existante</label>
                    <select class="form-select form-select-sm" name="html_list_id"
                            id="htmlListSelect" style="max-width:190px">
                        <?php foreach ($lists as $l): ?>
                        <option value="<?= View::e((string) $l['id']) ?>">
                            <?= View::e($l['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="small text-muted">ou</span>
                <div class="d-flex align-items-center gap-2">
                    <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="html_list_choice"
                           id="htmlListNew" value="new">
                    <label class="form-check-label small" for="htmlListNew">Nouvelle liste</label>
                    <input type="text" class="form-control form-control-sm" name="html_new_list_name"
                           id="htmlNewListName" placeholder="Nom de la liste"
                           style="max-width:160px" disabled>
                </div>
                <button type="submit" class="btn btn-sm btn-success ms-auto" id="btnImportHtml">
                    <span id="btnImportHtmlSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-filetype-html me-1" id="btnImportHtmlIcon"></i>Importer
                </button>
            </div>
        </div>

        <?php if (!empty($importHtmlResult)): ?>
        <div class="ks-migration-log mx-4 mb-3 mt-0" style="border-radius:10px">
            <?php if ($importHtmlResult['imported'] > 0): ?>
            <div class="ks-migration-line">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <?= $importHtmlResult['imported'] ?> favori<?= $importHtmlResult['imported'] > 1 ? 's' : '' ?> importé<?= $importHtmlResult['imported'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($importHtmlResult['folders_created'])): ?>
            <div class="ks-migration-line">
                <i class="bi bi-folder-plus text-primary me-2"></i>
                <?= $importHtmlResult['folders_created'] ?> dossier<?= $importHtmlResult['folders_created'] > 1 ? 's' : '' ?> créé<?= $importHtmlResult['folders_created'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($importHtmlResult['skipped'] > 0): ?>
            <div class="ks-migration-line">
                <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                <?= $importHtmlResult['skipped'] ?> entrée<?= $importHtmlResult['skipped'] > 1 ? 's' : '' ?> ignorée<?= $importHtmlResult['skipped'] > 1 ? 's' : '' ?> (URL non HTTP)
            </div>
            <?php endif; ?>
            <?php foreach ($importHtmlResult['errors'] as $err): ?>
            <div class="ks-migration-line">
                <i class="bi bi-x-circle-fill text-danger me-2"></i><?= View::e($err) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ── Section Importer — favoris KT-Start (v1) ──────────────────────────── -->
<p class="ks-section-label">Importer des favoris KT-Start</p>

<div class="ks-admin-card overflow-hidden p-0 mb-4">
    <form method="post" action="?action=admin_import" enctype="multipart/form-data" id="importForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <input type="hidden" name="full_restore" value="0">

        <div class="ks-backup-row px-4 py-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(99,102,241,.10);color:#6366f1">
                    <i class="bi bi-upload"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-0" style="font-size:.925rem">Export favoris <span class="badge text-bg-secondary ms-1" style="font-size:.7rem;vertical-align:middle">v1</span></p>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        Importe un fichier <code>ktstart-bookmarks-*.json</code>. Vos listes et favoris actuels sont remplacés — les comptes utilisateurs ne sont pas modifiés.
                    </p>
                </div>
            </div>

            <div class="ks-inset-divider"></div>

            <div class="d-flex flex-wrap align-items-center gap-2 ps-1 mt-3">
                <input type="file" class="form-control form-control-sm" name="import_file"
                       accept=".json,application/json" required style="max-width:280px">
                <button type="submit" class="btn btn-sm btn-primary" id="btnImport">
                    <span id="btnImportSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-upload me-1" id="btnImportIcon"></i><span id="btnImportLabel">Importer</span>
                </button>
            </div>
        </div>

        <?php if (!empty($importResult)): ?>
        <div class="ks-migration-log mx-4 mb-3 mt-0" style="border-radius:10px">
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
            <?php if (!empty($importResult['folders_created'])): ?>
            <div class="ks-migration-line">
                <i class="bi bi-folder-plus text-primary me-2"></i>
                <?= $importResult['folders_created'] ?> dossier<?= $importResult['folders_created'] > 1 ? 's' : '' ?> créé<?= $importResult['folders_created'] > 1 ? 's' : '' ?>
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
    </form>
</div>

<!-- ── Section Restauration complète (v2) ────────────────────────────────── -->
<p class="ks-section-label">Restauration complète</p>

<div class="ks-admin-card overflow-hidden p-0 mb-4" style="border:1px solid rgba(220,53,69,.25)">
    <form method="post" action="?action=admin_import" enctype="multipart/form-data" id="restoreForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <input type="hidden" name="full_restore" value="1">

        <div class="ks-backup-row px-4 py-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(220,53,69,.10);color:#dc3545">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-0" style="font-size:.925rem">Restaurer depuis un backup <span class="badge text-bg-primary ms-1" style="font-size:.7rem;vertical-align:middle">v2</span></p>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        Nécessite un fichier <code>ktstart-backup-*.json</code>. <strong class="text-danger">Toutes les données existantes sont effacées</strong> (comptes, listes, favoris, paramètres) avant la restauration. Vous serez déconnecté à l'issue.
                    </p>
                </div>
            </div>

            <div class="ks-inset-divider"></div>

            <div class="d-flex flex-wrap align-items-center gap-2 ps-1 mt-3">
                <input type="file" class="form-control form-control-sm" name="import_file" id="restoreFileInput"
                       accept=".json,application/json" required style="max-width:280px">
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnRestore"
                        data-bs-toggle="modal" data-bs-target="#restoreConfirmModal">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurer…
                </button>
            </div>
        </div>

        <?php if (!empty($importResult) && !empty($importResult['errors'])): ?>
        <div class="ks-migration-log mx-4 mb-3 mt-0" style="border-radius:10px">
            <?php foreach ($importResult['errors'] as $err): ?>
            <div class="ks-migration-line">
                <i class="bi bi-x-circle-fill text-danger me-2"></i><?= View::e($err) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- ── Modal confirmation restauration (style iOS) ───────────────────────── -->
<div class="modal fade" id="restoreConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:340px">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 8px 40px rgba(0,0,0,.18)">
            <div class="modal-body text-center px-4 pt-4 pb-3">
                <p class="fw-bold mb-1" style="font-size:1rem">Restauration complète</p>
                <p class="text-muted mb-0" style="font-size:.85rem;line-height:1.45">
                    Toutes les données actuelles (comptes, listes, favoris, paramètres) seront
                    <strong>définitivement effacées</strong> et remplacées par le contenu du fichier backup.
                    Vous serez déconnecté à l'issue.
                </p>
            </div>
            <div style="border-top:1px solid var(--bs-border-color);display:flex">
                <button type="button" class="btn btn-link flex-fill py-2" data-bs-dismiss="modal"
                        style="border-right:1px solid var(--bs-border-color);border-radius:0;font-size:.95rem;text-decoration:none">
                    Annuler
                </button>
                <button type="button" class="btn btn-link flex-fill py-2 text-danger fw-semibold" id="btnConfirmRestore"
                        style="border-radius:0;font-size:.95rem;text-decoration:none">
                    <span id="btnConfirmRestoreSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    Restaurer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script src="<?= View::asset('js/admin/backup.js') ?>"></script>
