<?php

use App\Core\View;

/** @var array $bookmarks */
/** @var string $csrf */
/** @var array|null $flash */

// Grouper par statut
$byStatus = ['error' => [], 'redirect' => [], 'timeout' => [], 'ok' => [], null => []];
foreach ($bookmarks as $bm) {
    $s = $bm['last_check_status'];
    if (!array_key_exists($s, $byStatus)) {
        $s = null;
    }
    $byStatus[$s][] = $bm;
}

$deadCount     = count($byStatus['error']) + count($byStatus['timeout']);
$redirectCount = count($byStatus['redirect']);
$neverChecked  = count($byStatus[null]);
$total         = count($bookmarks);
?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show mb-3 text-center" role="alert">
        <?= View::e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h4 class="mb-0 fw-bold">Vérification des liens</h4>
        <div class="text-muted small"><?= $total ?> favori<?= $total > 1 ? 's' : '' ?></div>
    </div>

    <div class="d-flex gap-2 ms-auto">
        <button id="btnCheckAll" class="btn btn-primary" data-csrf="<?= View::e($csrf) ?>">
            <i class="bi bi-arrow-repeat me-1"></i>Vérifier tous les liens
        </button>

        <form method="post" action="?action=bookmark_reset_status" class="d-inline"
              onsubmit="return confirm('Réinitialiser tous les statuts de vérification ?')">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button type="submit" class="btn btn-outline-secondary" title="Réinitialiser les statuts">
                <i class="bi bi-x-circle me-1"></i>Réinitialiser
            </button>
        </form>
    </div>

    <a href="?action=bookmarks" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<!-- Résumé -->
<div class="row g-3 mb-4" id="linksSummary">
    <div class="col-6 col-md-3">
        <div class="ks-admin-card text-center">
            <div class="fs-3 fw-bold text-success" id="countOk"><?= count($byStatus['ok']) ?></div>
            <div class="text-muted small">Accessibles</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ks-admin-card text-center">
            <div class="fs-3 fw-bold text-danger" id="countDead"><?= $deadCount ?></div>
            <div class="text-muted small">Inaccessibles</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ks-admin-card text-center">
            <div class="fs-3 fw-bold text-warning" id="countRedirect"><?= $redirectCount ?></div>
            <div class="text-muted small">Redirigés (301)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ks-admin-card text-center">
            <div class="fs-3 fw-bold text-secondary" id="countUnchecked"><?= $neverChecked ?></div>
            <div class="text-muted small">Non vérifiés</div>
        </div>
    </div>
</div>

<!-- Barre de progression -->
<div id="checkProgress" class="mb-4 d-none">
    <div class="d-flex justify-content-between small text-muted mb-1">
        <span>Vérification en cours…</span>
        <span id="progressLabel">0 / <?= $total ?></span>
    </div>
    <div class="progress" style="height:6px">
        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar" style="width:0%"></div>
    </div>
</div>

<!-- Table des favoris avec statut -->
<form method="post" action="?action=bookmark_delete_dead" id="deadLinksForm">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <?php
    $sections = [
        'error'    => ['label' => 'Inaccessibles (4xx / 5xx)', 'icon' => 'bi-x-circle-fill', 'color' => 'text-danger',  'bg' => 'table-danger'],
        'timeout'  => ['label' => 'Timeout / Hors ligne',      'icon' => 'bi-wifi-off',       'color' => 'text-danger',  'bg' => 'table-danger'],
        'redirect' => ['label' => 'Redirigés (301)',            'icon' => 'bi-arrow-right-circle-fill', 'color' => 'text-warning', 'bg' => 'table-warning'],
        'ok'       => ['label' => 'Accessibles',                'icon' => 'bi-check-circle-fill', 'color' => 'text-success', 'bg' => ''],
        null       => ['label' => 'Non vérifiés',               'icon' => 'bi-question-circle', 'color' => 'text-secondary', 'bg' => ''],
    ];
    ?>

    <?php foreach ($sections as $statusKey => $sec): ?>
        <?php $rows = $byStatus[$statusKey]; if (empty($rows)) continue; ?>
        <h6 class="mt-4 mb-2 <?= $sec['color'] ?>">
            <i class="bi <?= $sec['icon'] ?> me-1"></i><?= $sec['label'] ?> (<?= count($rows) ?>)
        </h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <?php if (in_array($statusKey, ['error', 'timeout'], true)): ?>
                        <th style="width:36px">
                            <input type="checkbox" class="form-check-input ks-check-section"
                                   data-section="<?= $statusKey ?>">
                        </th>
                        <?php else: ?>
                        <th style="width:36px"></th>
                        <?php endif; ?>
                        <th>Titre / URL</th>
                        <th style="width:100px">Code HTTP</th>
                        <th style="width:140px">Vérifié le</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $bm): ?>
                    <tr class="<?= $sec['bg'] ?>" data-id="<?= $bm['id'] ?>">
                        <td>
                            <?php if (in_array($statusKey, ['error', 'timeout'], true)): ?>
                            <input type="checkbox" class="form-check-input ks-dead-check"
                                   name="ids[]" value="<?= $bm['id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener"
                               class="fw-semibold text-decoration-none text-body">
                                <?= View::e($bm['title'] ?: $bm['host'] ?: $bm['url']) ?>
                            </a>
                            <div class="text-muted small text-truncate" style="max-width:400px">
                                <?= View::e($bm['url']) ?>
                            </div>
                        </td>
                        <td class="ks-http-code" data-id="<?= $bm['id'] ?>">
                            <?php if ($bm['last_check_status']): ?>
                                <span class="badge <?= $statusKey === 'ok' ? 'text-bg-success' : ($statusKey === 'redirect' ? 'text-bg-warning' : 'text-bg-danger') ?>">
                                    —
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small ks-checked-at" data-id="<?= $bm['id'] ?>">
                            <?= $bm['last_check_at'] ? View::e(substr($bm['last_check_at'], 0, 16)) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <!-- Bouton suppression en lot -->
    <div id="deadActionsBar" class="d-none mt-3 p-3 bg-danger bg-opacity-10 border border-danger rounded d-flex align-items-center gap-3">
        <span class="text-danger fw-semibold">
            <i class="bi bi-trash me-1"></i>
            <span id="selectedCount">0</span> favori(s) sélectionné(s)
        </span>
        <button type="submit" class="btn btn-danger btn-sm"
                onclick="return confirm('Supprimer les favoris sélectionnés ?')">
            Supprimer la sélection
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAll">
            Tout désélectionner
        </button>
    </div>
</form>

<script>
(function () {
    const csrf = document.querySelector('#btnCheckAll').dataset.csrf;
    // Liste des IDs dans l'ordre du DOM (toutes les lignes du tableau)
    const allIds = Array.from(document.querySelectorAll('tr[data-id]'))
                        .map(tr => tr.dataset.id);
    const total  = allIds.length;

    const counts = { ok: 0, error: 0, timeout: 0, redirect: 0 };

    // ── Vérification URL par URL ─────────────────────────────────────────────
    async function checkNext(ids, done) {
        if (ids.length === 0) {
            onAllDone();
            return;
        }
        const id  = ids[0];
        const rest = ids.slice(1);

        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('id', id);

        try {
            const r    = await fetch('?action=bookmark_check_single', { method: 'POST', body: fd });
            const data = await r.json();

            if (data.ok) {
                counts[data.status] = (counts[data.status] || 0) + 1;

                // Mettre à jour le code HTTP
                document.querySelectorAll(`.ks-http-code[data-id="${id}"]`).forEach(el => {
                    const cls = data.status === 'ok'
                        ? 'text-bg-success'
                        : (data.status === 'redirect' ? 'text-bg-warning' : 'text-bg-danger');
                    el.innerHTML = `<span class="badge ${cls}">${data.http_code || '—'}</span>`;
                });
                document.querySelectorAll(`.ks-checked-at[data-id="${id}"]`).forEach(el => {
                    el.textContent = data.checked_at.substring(0, 16);
                });
            }
        } catch (e) {
            // Erreur réseau : compter comme timeout
            counts.timeout = (counts.timeout || 0) + 1;
        }

        // Mise à jour barre de progression
        const doneCount = done + 1;
        const pct = Math.round(doneCount / total * 100);
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressLabel').textContent = `${doneCount} / ${total}`;

        // Mise à jour compteurs en temps réel
        document.getElementById('countOk').textContent       = counts.ok       || 0;
        document.getElementById('countDead').textContent     = (counts.error   || 0) + (counts.timeout || 0);
        document.getElementById('countRedirect').textContent = counts.redirect  || 0;
        document.getElementById('countUnchecked').textContent = total - doneCount;

        checkNext(rest, doneCount);
    }

    function onAllDone() {
        const btn = document.getElementById('btnCheckAll');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Revérifier';
        // Recharger pour regrouper par statut
        setTimeout(() => location.reload(), 1000);
    }

    document.getElementById('btnCheckAll').addEventListener('click', function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Vérification…';
        document.getElementById('checkProgress').classList.remove('d-none');
        document.getElementById('countUnchecked').textContent = total;
        counts.ok = counts.error = counts.timeout = counts.redirect = 0;

        checkNext(allIds, 0);
    });

    // ── Sélection en lot ─────────────────────────────────────────────────────
    function updateActionBar() {
        const n   = document.querySelectorAll('.ks-dead-check:checked').length;
        const bar = document.getElementById('deadActionsBar');
        bar.classList.toggle('d-none', n === 0);
        document.getElementById('selectedCount').textContent = n;
    }

    document.querySelectorAll('.ks-dead-check').forEach(cb => {
        cb.addEventListener('change', updateActionBar);
    });

    document.querySelectorAll('.ks-check-section').forEach(cb => {
        cb.addEventListener('change', function () {
            document.querySelectorAll('.ks-dead-check').forEach(c => {
                c.checked = this.checked;
            });
            updateActionBar();
        });
    });

    document.getElementById('btnDeselectAll')?.addEventListener('click', () => {
        document.querySelectorAll('.ks-dead-check, .ks-check-section').forEach(c => c.checked = false);
        updateActionBar();
    });
})();
</script>
