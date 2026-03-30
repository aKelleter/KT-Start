<?php use App\Core\View; ?>

<div class="app-login-wrap">
    <div style="width:100%;max-width:420px">
        <div class="app-card shadow-soft p-4 p-md-5">

            <div class="text-center mb-4">
                <i class="bi bi-bookmark-star-fill app-brand-icon" style="font-size:2.2rem"></i>
                <h1 class="h4 mt-2 fw-bold" style="letter-spacing:-0.02em">KT-Start</h1>
                <p class="text-muted small mb-0">Gestionnaire de favoris</p>
            </div>

            <?php if (!empty($flash)): ?>
                <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show text-center" role="alert">
                    <?= View::e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="?action=login_submit">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label app-label">Email</label>
                    <input
                        type="email"
                        class="form-control app-input"
                        id="email"
                        name="email"
                        required
                        autofocus
                    >
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label app-label">Mot de passe</label>
                    <input
                        type="password"
                        class="form-control app-input"
                        id="password"
                        name="password"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-blue w-100 fw-semibold">
                    Se connecter
                </button>
            </form>

        </div>
    </div>
</div>
