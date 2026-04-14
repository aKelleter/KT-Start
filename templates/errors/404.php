<?php /** @var string $requestedAction */ ?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 60vh;">
    <div class="text-center" style="max-width: 480px; width: 100%;">

        <div class="ks-404-card mx-auto">
            <div class="ks-404-icon mb-4">
                <i class="bi bi-compass" style="font-size: 3rem; color: var(--app-blue);"></i>
            </div>

            <p class="ks-404-code">404</p>

            <h1 class="ks-404-title">Page introuvable</h1>

            <p class="ks-404-desc">
                La page que vous recherchez n'existe pas ou a été déplacée.
            </p>

            <?php if (!empty($requestedAction)): ?>
            <p class="ks-404-action-name">
                <code><?= \App\Core\View::e($requestedAction) ?></code>
            </p>
            <?php endif; ?>

            <div class="ks-404-divider"></div>

            <div class="d-flex flex-column gap-2">
                <a href="?action=bookmarks" class="btn btn-primary w-100">
                    <i class="bi bi-house me-2"></i>Mes favoris
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-2"></i>Page précédente
                </a>
            </div>
        </div>

    </div>
</div>
