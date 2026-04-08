<?php

use App\Core\View;

?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show mb-4 text-center" role="alert">
        <?= View::e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <div class="ks-admin-icon"><i class="bi bi-gear-fill"></i></div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Administration</h1>
        <p class="text-muted small mb-0">Gérez les paramètres et les ressources de l'application.</p>
    </div>
    <a href="?action=bookmarks" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<div class="row g-3">
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_users" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(2,136,209,.10);color:#0288D1">
                <i class="bi bi-people-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Utilisateurs
                <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.7rem"><?= $userCount ?></span>
            </p>
            <p class="text-muted small mb-0">Créer, modifier et supprimer les comptes. Gérer les rôles d'accès.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_lists" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(99,102,241,.10);color:#6366f1">
                <i class="bi bi-collection-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Listes
                <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.7rem"><?= $listCount ?></span>
            </p>
            <p class="text-muted small mb-0">Organiser vos favoris en listes thématiques.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_settings" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(16,185,129,.10);color:#10b981">
                <i class="bi bi-sliders"></i>
            </div>
            <p class="fw-semibold mb-1">Paramètres</p>
            <p class="text-muted small mb-0">Configurer les options de l'application.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_backup" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(245,158,11,.10);color:#f59e0b">
                <i class="bi bi-archive-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Sauvegarde</p>
            <p class="text-muted small mb-0">Exporter et restaurer vos données au format JSON.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_tags" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(20,184,166,.10);color:#14b8a6">
                <i class="bi bi-tags-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Tags
                <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.7rem"><?= $tagCount ?></span>
            </p>
            <p class="text-muted small mb-0">Renommer et supprimer les tags de tous les favoris.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_maintenance" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(239,68,68,.10);color:#ef4444">
                <i class="bi bi-wrench-adjustable-circle-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Maintenance</p>
            <p class="text-muted small mb-0">Gérer les migrations et la structure de la base de données.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_folders" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(245,158,11,.10);color:#f59e0b">
                <i class="bi bi-folder-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Dossiers
                <span class="badge text-bg-secondary fw-normal ms-1" style="font-size:.7rem"><?= $folderCount ?></span>
            </p>
            <p class="text-muted small mb-0">Créer, organiser et hiérarchiser les dossiers par liste.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=admin_stats" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(99,102,241,.10);color:#6366f1">
                <i class="bi bi-bar-chart-fill"></i>
            </div>
            <p class="fw-semibold mb-1">Statistiques</p>
            <p class="text-muted small mb-0">Vue d'ensemble des favoris par liste, tag, statut et activité mensuelle.</p>
        </a>
    </div>
    <div class="col-sm-6 col-lg-4">
        <a href="?action=bookmark_links_report" class="ks-admin-nav-card">
            <div class="ks-admin-icon mb-3" style="background:rgba(234,88,12,.10);color:#ea580c">
                <i class="bi bi-link-45deg"></i>
            </div>
            <p class="fw-semibold mb-1">Vérification des liens
                <?php if ($deadLinkCount > 0): ?>
                <span class="badge text-bg-danger fw-normal ms-1" style="font-size:.7rem"><?= $deadLinkCount ?></span>
                <?php endif; ?>
            </p>
            <p class="text-muted small mb-0">Vérifier l'accessibilité des favoris et mettre à jour les redirections.</p>
        </a>
    </div>
</div>
