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

    <!-- Backup complet -->
    <div class="ks-backup-row d-flex align-items-center gap-3 px-4 py-3">
        <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
            <i class="bi bi-database-down"></i>
        </div>
        <div class="flex-grow-1">
            <p class="fw-semibold mb-0" style="font-size:.925rem">Backup complet</p>
            <p class="text-muted mb-0" style="font-size:.82rem">Utilisateurs, paramètres, listes et favoris (tous utilisateurs).</p>
        </div>
        <a href="?action=admin_export_full" class="btn btn-sm btn-primary flex-shrink-0">
            <i class="bi bi-database-down me-1"></i>Backup
        </a>
    </div>

    <div class="ks-inset-divider"></div>

    <!-- Export favoris -->
    <div class="ks-backup-row d-flex align-items-center gap-3 px-4 py-3">
        <div class="ks-admin-icon flex-shrink-0" style="background:rgba(2,136,209,.10);color:#0288D1">
            <i class="bi bi-download"></i>
        </div>
        <div class="flex-grow-1">
            <p class="fw-semibold mb-0" style="font-size:.925rem">Favoris uniquement</p>
            <p class="text-muted mb-0" style="font-size:.82rem">Vos favoris et listes au format JSON portable.</p>
        </div>
        <a href="?action=admin_export" class="btn btn-sm btn-outline-secondary flex-shrink-0">
            <i class="bi bi-download me-1"></i>Exporter
        </a>
    </div>

</div>

<!-- ── Section Importer ───────────────────────────────────────────────────── -->
<p class="ks-section-label">Importer</p>

<div class="ks-admin-card overflow-hidden p-0 mb-3">

    <!-- Import HTML navigateur -->
    <form method="post" action="?action=admin_import_html" enctype="multipart/form-data" id="importHtmlForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

        <div class="ks-backup-row px-4 py-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(34,197,94,.10);color:#16a34a">
                    <i class="bi bi-filetype-html"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-0" style="font-size:.925rem">Depuis un navigateur</p>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        Fichier HTML ou JSON Firefox — Chrome, Safari, Edge aussi. Les dossiers sont recréés dans la liste cible.
                    </p>
                </div>
            </div>
            <!-- Contrôles import HTML -->
            <!-- Ligne 1 : fichier -->
            <div class="ps-1 mb-2">
                <input type="file" class="form-control form-control-sm" name="import_html_file"
                       accept=".html,.htm,.json,text/html,application/json" required style="max-width:300px">
            </div>
            <!-- Ligne 2 : destination + bouton -->
            <div class="d-flex flex-wrap align-items-center gap-3 ps-1">
                <span class="small text-muted flex-shrink-0">Ajouter à</span>
                <!-- Choix liste existante -->
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
                <!-- Choix nouvelle liste -->
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

    <div class="ks-inset-divider"></div>

    <!-- Import JSON KT-Start -->
    <form method="post" action="?action=admin_import" enctype="multipart/form-data" id="importForm">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <input type="hidden" name="full_restore" id="fullRestoreInput" value="0">

        <div class="ks-backup-row px-4 py-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ks-admin-icon flex-shrink-0" style="background:rgba(99,102,241,.10);color:#6366f1">
                    <i class="bi bi-upload"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-0" style="font-size:.925rem">Depuis KT-Start (JSON)</p>
                    <p class="text-muted mb-0" style="font-size:.82rem">
                        Export favoris (v1) : remplace vos listes et favoris.&ensp;·&ensp;Backup complet (v2) : utilisez "Restauration complète".
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 ps-1">
                <input type="file" class="form-control form-control-sm" name="import_file"
                       accept=".json,application/json" required style="max-width:240px">
                <button type="submit" class="btn btn-sm btn-primary" id="btnImport">
                    <span id="btnImportSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-upload me-1" id="btnImportIcon"></i><span id="btnImportLabel">Importer</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnFullRestore">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restauration complète
                </button>
            </div>

            <div id="fullRestoreWarning" class="alert alert-danger py-2 px-3 mb-0 mt-3 small d-none" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Attention :</strong> toutes les données existantes (favoris, listes, utilisateurs, paramètres)
                seront <strong>définitivement effacées</strong> avant l'import.
                Vous serez déconnecté à l'issue de l'opération.
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
            <?php if (!empty($importResult['users_created'])): ?>
            <div class="ks-migration-line">
                <i class="bi bi-person-check-fill text-success me-2"></i>
                <?= $importResult['users_created'] ?> utilisateur<?= $importResult['users_created'] > 1 ? 's' : '' ?> créé<?= $importResult['users_created'] > 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($importResult['users_skipped'])): ?>
            <div class="ks-migration-line">
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

    </form>

</div>

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script src="<?= View::asset('js/admin/backup.js') ?>"></script>
