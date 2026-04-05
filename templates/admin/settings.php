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
        <div class="form-text mt-2 mb-4">
            La valeur en base de données prend le dessus sur <code>.env</code>.
        </div>

        <hr class="my-3">

        <div class="row align-items-center g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Proxy HTTP pour la vérification des liens
                    <?php if (!empty($envProxy)): ?>
                        <span class="text-muted fw-normal small ms-1">
                            (.env : <?= View::e($envProxy) ?>)
                        </span>
                    <?php endif; ?>
                </label>
                <input type="text" class="form-control ks-proxy-input" name="check_proxy"
                       placeholder="http://proxy.example.com:3128"
                       value="<?= View::e($settings['check_proxy'] ?? '') ?>">
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="check_proxy_enabled"
                               id="checkProxyEnabled" value="1"
                               <?= ($settings['check_proxy_enabled'] ?? '1') !== '0' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="checkProxyEnabled">
                            Activer le proxy
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer
                </button>
            </div>
        </div>

    </form>
</div>

<!-- ── Bookmarklet ────────────────────────────────────────────────────────── -->
<?php
$bookmarkletJs = "javascript:(function(){var u=encodeURIComponent(location.href);var t=encodeURIComponent(document.title);window.open('" . $appUrl . "?action=bookmarklet&url='+u+'&title='+t,'kt-start','width=480,height=580,resizable=yes,scrollbars=yes');})();";
?>
<div class="ks-admin-card p-4 mt-4">
    <h6 class="fw-bold mb-1">
        <i class="bi bi-bookmark-plus me-2 text-primary"></i>Bookmarklet
    </h6>
    <p class="text-muted small mb-3">
        Glissez ce bouton dans votre barre de favoris pour ajouter n'importe quelle page en un clic.
    </p>

    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="<?= View::e($bookmarkletJs) ?>"
           class="btn btn-primary btn-sm"
           onclick="return false;"
           title="Glissez ce bouton dans votre barre de favoris">
            <i class="bi bi-bookmark-plus me-1"></i>+ KT-Start
        </a>
        <span class="text-muted small">← Glissez ce bouton dans votre barre de favoris</span>
    </div>

    <div class="mb-0">
        <label class="form-label small fw-semibold">Code du bookmarklet</label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm font-monospace"
                   id="bookmarkletCode"
                   value="<?= View::e($bookmarkletJs) ?>"
                   readonly
                   style="font-size:.72rem">
            <button class="btn btn-outline-secondary" type="button" id="btnCopyBookmarklet"
                    title="Copier">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <div class="form-text">
            Si le glisser-déposer ne fonctionne pas, copiez ce code et créez manuellement un favori avec cette URL.
        </div>
    </div>
</div>

<script>
document.getElementById('btnCopyBookmarklet').addEventListener('click', function () {
    navigator.clipboard.writeText(document.getElementById('bookmarkletCode').value).then(() => {
        const icon = this.querySelector('i');
        icon.className = 'bi bi-check-lg text-success';
        setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
    });
});
</script>
