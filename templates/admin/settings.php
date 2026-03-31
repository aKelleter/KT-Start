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
    <div class="ks-admin-icon" style="background:rgba(16,185,129,.10);color:#10b981">
        <i class="bi bi-sliders"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Paramètres</h1>
        <p class="text-muted small mb-0">Configurer les options de l'application.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Formulaire paramètres ──────────────────────────────────────────────── -->
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
