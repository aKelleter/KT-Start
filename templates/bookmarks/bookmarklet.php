<?php
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KT-Start — Ajouter un favori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --app-blue: #0288D1;
            --app-radius: 12px;
        }
        body {
            background: #f5f7fa;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: .9rem;
            padding: 1rem;
            min-height: 100vh;
        }
        .bml-header {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1rem;
            padding-bottom: .75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .bml-header-icon {
            width: 32px; height: 32px;
            background: var(--app-blue);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .bml-title { font-weight: 700; font-size: .95rem; color: #1a1a2e; }
        .bml-subtitle { font-size: .75rem; color: #6b7280; }
        .form-label { font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .25rem; }
        .form-control, .form-select {
            font-size: .85rem;
            border-radius: 8px;
            border-color: #d1d5db;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--app-blue);
            box-shadow: 0 0 0 3px rgba(2,136,209,.15);
        }
        .btn-primary {
            background: var(--app-blue);
            border-color: var(--app-blue);
            border-radius: 8px;
            font-size: .85rem;
        }
        .btn-primary:hover { background: #0277bd; border-color: #0277bd; }
        .btn-secondary { border-radius: 8px; font-size: .85rem; }
        .ks-color-swatch-mini { display: flex; flex-wrap: wrap; gap: .3rem; }
        .ks-dot-mini {
            display: inline-block;
            width: 20px; height: 20px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color .12s, transform .12s;
        }
        .ks-dot-mini:hover { transform: scale(1.15); }
        input[name="badge_style"]:checked + .ks-dot-mini {
            border-color: #1a1a2e;
            transform: scale(1.15);
        }
        /* État succès */
        .bml-success {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center;
            min-height: 60vh;
            gap: 1rem;
        }
        .bml-success-icon {
            width: 56px; height: 56px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            color: #059669;
        }
        /* État non connecté */
        .bml-unauth {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center;
            min-height: 60vh;
            gap: 1rem;
        }
    </style>
</head>
<body>

<?php if ($notLogged): ?>
<!-- ── Non connecté ─────────────────────────────────────────────────────── -->
<div class="bml-unauth">
    <div style="font-size:2.5rem">🔒</div>
    <div>
        <p class="fw-semibold mb-1">Vous n'êtes pas connecté.</p>
        <p class="text-muted small mb-3">Connectez-vous à KT-Start pour ajouter des favoris.</p>
        <a href="?action=login" target="_blank" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
        </a>
    </div>
</div>

<?php elseif ($saved): ?>
<!-- ── Succès ────────────────────────────────────────────────────────────── -->
<div class="bml-success">
    <div class="bml-success-icon"><i class="bi bi-check-lg"></i></div>
    <div>
        <p class="fw-semibold mb-1">Favori ajouté !</p>
        <p class="text-muted small mb-3"><?= View::e($savedTitle) ?></p>
        <button class="btn btn-secondary btn-sm" onclick="window.close()">
            <i class="bi bi-x-lg me-1"></i>Fermer
        </button>
    </div>
</div>

<?php else: ?>
<!-- ── Formulaire ────────────────────────────────────────────────────────── -->

<div class="bml-header">
    <div class="bml-header-icon"><i class="bi bi-bookmark-plus"></i></div>
    <div>
        <div class="bml-title">Ajouter un favori</div>
        <div class="bml-subtitle">KT-Start</div>
    </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger py-2 px-3 small mb-3"><?= View::e($error) ?></div>
<?php endif; ?>

<form method="post" action="?action=bookmarklet_store">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="host" id="bmlHost" value="<?= View::e($host ?? '') ?>">

    <div class="mb-2">
        <label class="form-label">URL</label>
        <input type="url" class="form-control" name="url" id="bmlUrl"
               value="<?= View::e($url ?? '') ?>" required
               oninput="updateHost(this.value)">
    </div>

    <div class="mb-2">
        <label class="form-label">Titre</label>
        <input type="text" class="form-control" name="title"
               value="<?= View::e($title ?? '') ?>">
    </div>

    <div class="mb-2">
        <label class="form-label">Liste</label>
        <select class="form-select" name="list_id">
            <option value="">— Aucune —</option>
            <?php foreach ($lists as $list): ?>
                <option value="<?= (int) $list['id'] ?>"><?= View::e($list['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2">
        <label class="form-label">Tags <span class="fw-normal text-muted">(séparés par des virgules)</span></label>
        <input type="text" class="form-control" name="tags" placeholder="php, dev, outil">
    </div>

    <div class="mb-2">
        <label class="form-label">Couleur</label>
        <div class="ks-color-swatch-mini">
            <?php foreach ($badgeStyles as $key => $style): ?>
                <label title="<?= View::e($style['label']) ?>">
                    <input type="radio" name="badge_style" value="<?= View::e($key) ?>"
                           class="d-none" <?= $key === 'deepBlue' ? 'checked' : '' ?>>
                    <span class="ks-dot-mini" style="background:<?= View::e($style['bg']) ?>"></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Visibilité</label>
        <select class="form-select" name="visibility">
            <option value="private">Privé</option>
            <option value="public">Public</option>
        </select>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bi bi-bookmark-plus me-1"></i>Ajouter
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.close()">
            Annuler
        </button>
    </div>
</form>

<script>
function updateHost(url) {
    try {
        document.getElementById('bmlHost').value = new URL(url).hostname;
    } catch (e) { /* URL incomplète */ }
}
</script>

<?php endif; ?>

</body>
</html>
