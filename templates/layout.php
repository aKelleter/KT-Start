<?php

use App\Config\Config;
use App\Core\Auth;
use App\Core\View;

$appName    = Config::get('APP_NAME', 'KT-Start');
$appVersion = Config::get('APP_VERSION', '1.0.0');
$appUpdate  = Config::get('APP_UPDATE', '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= View::e((string) $appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <link rel="icon" href="<?= View::asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= View::asset('css/app.css') ?>">
</head>
<body class="app-body">

<div class="app-shell">

    <!-- ── Navbar ──────────────────────────────────────────────────────── -->
    <nav class="navbar navbar-expand-lg app-navbar app-navbar-fixed">
        <div class="container">
            <a class="navbar-brand app-brand d-flex align-items-center gap-2" href="?action=bookmarks">
                <i class="bi bi-bookmark-star-fill app-brand-icon"></i>
                <span><?= View::e((string) $appName) ?></span>
            </a>

            <?php if (Auth::check()): ?>
                <div class="d-flex align-items-center gap-3">
                    <span class="small app-user-email d-none d-md-inline">
                        <?= View::e(Auth::user()['email'] ?? '') ?>
                    </span>
                    <a href="?action=bookmarks" class="btn btn-outline-blue btn-sm" title="Mes favoris">
                        <i class="bi bi-house"></i>
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="?action=admin" class="btn btn-outline-blue btn-sm">
                        <i class="bi bi-gear me-1"></i>Admin
                    </a>
                    <?php endif; ?>
                    <a href="?action=logout" class="btn btn-outline-blue btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                    </a>
                </div>
            <?php else: ?>
                <a href="?action=login" class="btn btn-outline-blue btn-sm">
                    <i class="bi bi-person me-1"></i>Connexion
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ── Contenu ─────────────────────────────────────────────────────── -->
    <main class="app-main py-4">
        <div class="container">
            <?php require BASE_PATH . '/templates/' . $template . '.php'; ?>
        </div>
    </main>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="app-footer">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><?= View::e((string) $appName) ?></span>
            <span>v<?= View::e((string) $appVersion) ?> - <?= View::e((string) $appUpdate) ?></span>
            <a href="https://github.com/aKelleter/KT-Start" target="_blank" rel="noopener noreferrer"
               class="text-secondary" title="GitHub">
                <i class="bi bi-github fs-5"></i>
            </a>
        </div>
    </footer>

</div>

<!-- Back to top -->
<button id="back-to-top" class="back-to-top" aria-label="Haut de page" title="Haut de page">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="<?= View::asset('js/app.js') ?>"></script>
</body>
</html>
