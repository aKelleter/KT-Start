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
    <div class="ks-admin-icon" style="background:rgba(239,68,68,.10);color:#ef4444">
        <i class="bi bi-wrench-adjustable-circle-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Maintenance</h1>
        <p class="text-muted small mb-0">Gérer les migrations et la structure de la base de données.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Migration ──────────────────────────────────────────────────────────── -->
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
