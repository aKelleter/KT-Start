<?php
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="fr" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KT-Start — Ajouter un favori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script>
        (function() {
            var t = localStorage.getItem('ks-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <style>
        :root {
            --app-blue: #0288D1;
            --bml-bg:      #f5f7fa;
            --bml-card:    #ffffff;
            --bml-text:    #1a1a2e;
            --bml-muted:   #6b7280;
            --bml-label:   #374151;
            --bml-border:  #d1d5db;
            --bml-sep:     #e5e7eb;
            --bml-input-bg:#ffffff;
            --bml-dot-sel: #1a1a2e;
        }
        [data-theme="dark"] {
            --bml-bg:      #111113;
            --bml-card:    #1c1c1e;
            --bml-text:    #f2f2f7;
            --bml-muted:   #98989d;
            --bml-label:   #c7c7cc;
            --bml-border:  #3a3a3c;
            --bml-sep:     #2c2c2e;
            --bml-input-bg:#2c2c2e;
            --bml-dot-sel: #f2f2f7;
        }
        body {
            background: var(--bml-bg);
            color: var(--bml-text);
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
            border-bottom: 1px solid var(--bml-sep);
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
        .bml-title   { font-weight: 700; font-size: .95rem; color: var(--bml-text); }
        .bml-subtitle { font-size: .75rem; color: var(--bml-muted); }
        .form-label  { font-size: .8rem; font-weight: 600; color: var(--bml-label); margin-bottom: .25rem; }
        .form-control, .form-select {
            font-size: .85rem;
            border-radius: 8px;
            border-color: var(--bml-border);
            background-color: var(--bml-input-bg);
            color: var(--bml-text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--app-blue);
            box-shadow: 0 0 0 3px rgba(2,136,209,.15);
            background-color: var(--bml-input-bg);
            color: var(--bml-text);
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
            border-color: var(--bml-dot-sel);
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
        [data-theme="dark"] .bml-success-icon {
            background: #052e16;
            color: #34d399;
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
        <select class="form-select" name="list_id" id="bmlListId">
            <option value="">— Aucune —</option>
            <?php foreach ($lists as $list): ?>
                <option value="<?= (int) $list['id'] ?>"<?= !empty($list['is_default']) ? ' selected' : '' ?>>
                    <?= View::e($list['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-2" id="bmlFolderWrap" style="display:none">
        <label class="form-label">Dossier</label>
        <select class="form-select" name="folder_id" id="bmlFolderId">
            <option value="">— Racine de la liste —</option>
        </select>
    </div>

    <div class="mb-2">
        <label class="form-label">Tags <span class="fw-normal text-muted">(séparés par des virgules)</span></label>
        <input type="text" class="form-control" name="tags" id="bmlTags" placeholder="php, dev, outil" autocomplete="off">
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


<?php endif; ?>

<script type="application/json" id="ks-bml-data">
<?= json_encode([
    'folders' => array_values($folders ?? []),
    'tags'    => $allTags ?? [],
], JSON_UNESCAPED_UNICODE) ?>
</script>
<script src="<?= View::asset('js/bookmarklet.js') ?>"></script>
</body>
</html>
