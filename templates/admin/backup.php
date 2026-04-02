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
        <p class="text-muted small mb-0">Exporter et restaurer vos données au format JSON.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Contenu sauvegarde ─────────────────────────────────────────────────── -->
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
                        <span id="btnImportSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-upload me-1" id="btnImportIcon"></i><span id="btnImportLabel">Importer</span>
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

<!-- ── JS ─────────────────────────────────────────────────────────────────── -->
<script>
(function () {

    // ── Restauration complète ────────────────────────────────────────────
    const btnFullRestore     = document.getElementById('btnFullRestore');
    const fullRestoreInput   = document.getElementById('fullRestoreInput');
    const fullRestoreWarning = document.getElementById('fullRestoreWarning');
    const importForm         = document.getElementById('importForm');

    if (btnFullRestore) {
        btnFullRestore.addEventListener('click', function () {
            if (fullRestoreInput.value === '1') {
                // Déjà en mode restauration → soumettre avec confirmation
                if (confirm('Confirmer la restauration complète ?\n\nToutes les données actuelles seront effacées et remplacées par le contenu du fichier. Cette action est irréversible.')) {
                    showImportSpinner(true);
                    importForm.submit();
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

    // ── Spinner import ───────────────────────────────────────────────────
    function showImportSpinner(forRestore) {
        const spinner = document.getElementById('btnImportSpinner');
        const icon    = document.getElementById('btnImportIcon');
        const label   = document.getElementById('btnImportLabel');
        const btn     = document.getElementById('btnImport');
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        label.textContent = forRestore ? 'Restauration…' : 'Import en cours…';
        btn.disabled = true;
        btnFullRestore.disabled = true;
    }

    if (importForm) {
        importForm.addEventListener('submit', function () {
            const isRestore = fullRestoreInput.value === '1';
            showImportSpinner(isRestore);
        });
    }

})();
</script>
