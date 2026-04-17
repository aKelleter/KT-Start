<?php

use App\Core\View;

/** @var array $bookmarks */
/** @var string $csrf */
/** @var array|null $flash */

// Grouper par statut (les exclus sont mis de côté)
$byStatus  = ['error' => [], 'redirect' => [], 'timeout' => [], 'ok' => [], '' => []];
$skipped   = [];
foreach ($bookmarks as $bm) {
    if (!empty($bm['check_skip'])) {
        $skipped[] = $bm;
        continue;
    }
    $s = (string) ($bm['last_check_status'] ?? '');
    if (!array_key_exists($s, $byStatus)) {
        $s = '';
    }
    $byStatus[$s][] = $bm;
}

$deadCount     = count($byStatus['error']) + count($byStatus['timeout']);
$redirectCount = count($byStatus['redirect']);
$neverChecked  = count($byStatus['']);
$total         = count($bookmarks) - count($skipped);
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

    <div class="d-flex gap-2 ms-auto flex-wrap">
        <!-- Bouton principal : "Vérifier" ou "Continuer (N)" selon l'état -->
        <button id="btnCheckPending" class="btn btn-primary" data-csrf="<?= View::e($csrf) ?>">
            <?php if ($neverChecked > 0 && $neverChecked < $total): ?>
                <i class="bi bi-play-fill me-1"></i>Continuer (<?= $neverChecked ?> restant<?= $neverChecked > 1 ? 's' : '' ?>)
            <?php else: ?>
                <i class="bi bi-arrow-repeat me-1"></i>Vérifier tous les liens
            <?php endif; ?>
        </button>

        <!-- Tout revérifier : visible uniquement quand une partie est déjà vérifiée -->
        <?php if ($neverChecked > 0 && $neverChecked < $total): ?>
        <button id="btnCheckAll" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-repeat me-1"></i>Tout revérifier
        </button>
        <?php else: ?>
        <button id="btnCheckAll" class="btn btn-outline-secondary d-none">
            <i class="bi bi-arrow-repeat me-1"></i>Tout revérifier
        </button>
        <?php endif; ?>

        <!-- Bouton Stop (caché au départ) -->
        <button id="btnStop" class="btn btn-danger d-none">
            <i class="bi bi-stop-fill me-1"></i>Arrêter
        </button>

        <form method="post" action="?action=bookmark_reset_status" class="d-inline" id="resetStatusForm">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button type="button" class="btn btn-outline-secondary" id="btnResetStatus" title="Réinitialiser les statuts">
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
        ''         => ['label' => 'Non vérifiés',               'icon' => 'bi-question-circle', 'color' => 'text-secondary', 'bg' => ''],
    ];
    ?>

    <?php foreach ($sections as $statusKey => $sec): ?>
        <?php $rows = $byStatus[$statusKey]; if (empty($rows)) continue; ?>
        <h6 class="mt-4 mb-2 <?= $sec['color'] ?>">
            <i class="bi <?= $sec['icon'] ?> me-1"></i><?= $sec['label'] ?> (<?= count($rows) ?>)
        </h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle ks-table">
                <thead class="table-light">
                    <tr>
                        <?php if (in_array($statusKey, ['error', 'timeout'], true)): ?>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-trash text-danger" style="font-size:.7rem;opacity:.7" title="Supprimer"></i>
                                <input type="checkbox" class="form-check-input ks-check-section"
                                       data-section="<?= $statusKey ?>" title="Tout sélectionner pour suppression">
                            </div>
                        </th>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-arrow-repeat text-primary" style="font-size:.7rem;opacity:.7" title="Revérifier"></i>
                                <input type="checkbox" class="form-check-input ks-check-recheck-section"
                                       title="Tout sélectionner pour revérification">
                            </div>
                        </th>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-slash-circle text-secondary" style="font-size:.7rem;opacity:.7" title="Exclure"></i>
                                <input type="checkbox" class="form-check-input ks-check-skip-section"
                                       title="Tout sélectionner pour exclusion">
                            </div>
                        </th>
                        <?php elseif ($statusKey === 'redirect'): ?>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-arrow-right-circle text-warning" style="font-size:.7rem;opacity:.7" title="Mettre à jour l'URL"></i>
                                <input type="checkbox" class="form-check-input ks-check-redirect-section"
                                       title="Tout sélectionner pour mise à jour URL">
                            </div>
                        </th>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-slash-circle text-secondary" style="font-size:.7rem;opacity:.7" title="Exclure"></i>
                                <input type="checkbox" class="form-check-input ks-check-skip-section"
                                       title="Tout sélectionner pour exclusion">
                            </div>
                        </th>
                        <?php else: ?>
                        <th style="width:32px"></th>
                        <th style="width:32px" class="text-center">
                            <div class="d-flex flex-column align-items-center gap-1">
                                <i class="bi bi-slash-circle text-secondary" style="font-size:.7rem;opacity:.7" title="Exclure"></i>
                                <input type="checkbox" class="form-check-input ks-check-skip-section"
                                       title="Tout sélectionner pour exclusion">
                            </div>
                        </th>
                        <?php endif; ?>
                        <th>Titre / URL</th>
                        <th style="width:100px">Code HTTP</th>
                        <th style="width:140px">Vérifié le</th>
                        <th style="width:68px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $bm): ?>
                    <tr class="<?= $sec['bg'] ?>" data-id="<?= $bm['id'] ?>" data-status="<?= View::e($bm['last_check_status'] ?? '') ?>">
                        <?php if (in_array($statusKey, ['error', 'timeout'], true)): ?>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-dead-check"
                                   name="ids[]" value="<?= $bm['id'] ?>" title="Sélectionner pour suppression">
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-recheck-check"
                                   value="<?= $bm['id'] ?>" title="Sélectionner pour revérification">
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-skip-check"
                                   value="<?= $bm['id'] ?>" title="Sélectionner pour exclusion">
                        </td>
                        <?php elseif ($statusKey === 'redirect'): ?>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-redirect-check"
                                   value="<?= $bm['id'] ?>" title="Sélectionner pour mise à jour URL">
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-skip-check"
                                   value="<?= $bm['id'] ?>" title="Sélectionner pour exclusion">
                        </td>
                        <?php else: ?>
                        <td></td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input ks-skip-check"
                                   value="<?= $bm['id'] ?>" title="Sélectionner pour exclusion">
                        </td>
                        <?php endif; ?>
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
                                <?php $badgeCls = $statusKey === 'ok' ? 'text-bg-success' : ($statusKey === 'redirect' ? 'text-bg-warning' : 'text-bg-danger'); ?>
                                <?php
                                    $code    = (int) ($bm['last_http_code'] ?? 0);
                                    $display = $code > 0 ? $code : ($statusKey === 'timeout' ? 'Timeout' : '—');
                                ?>
                                <span class="badge <?= $badgeCls ?>"><?= $display ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small ks-checked-at" data-id="<?= $bm['id'] ?>">
                            <?= $bm['last_check_at'] ? View::e(substr($bm['last_check_at'], 0, 16)) : '—' ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if (in_array($statusKey, ['error', 'timeout'], true)): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary ks-recheck-btn p-1 lh-1 me-1"
                                    data-id="<?= $bm['id'] ?>"
                                    title="Revérifier ce lien">
                                <i class="bi bi-arrow-repeat" style="font-size:.85rem"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary ks-skip-btn p-1 lh-1"
                                    data-id="<?= $bm['id'] ?>"
                                    title="Exclure de la vérification">
                                <i class="bi bi-slash-circle" style="font-size:.85rem"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <!-- Barre suppression en lot -->
    <div id="deadActionsBar" class="d-none"
         style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
                width:min(300px,calc(100vw - 2rem));z-index:1025;
                background:var(--bs-body-bg);border-radius:16px;
                box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
        <div class="text-center px-4 py-3">
            <div class="text-danger fw-semibold">
                <i class="bi bi-trash me-1"></i><span id="selectedCount">0</span> favori(s) sélectionné(s)
            </div>
        </div>
        <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
            <button type="button" id="btnDeselectAll"
                    class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                    style="border-radius:0;border-right:1px solid var(--bs-border-color)">
                Annuler
            </button>
            <button type="button" id="btnDeleteDeadSelected"
                    class="btn btn-link flex-fill text-danger fw-semibold py-2 px-3"
                    style="border-radius:0">
                Supprimer
            </button>
        </div>
    </div>
</form>

<!-- Barre exclusion en lot -->
<div id="skipActionsBar" class="d-none"
     style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:min(300px,calc(100vw - 2rem));z-index:1025;
            background:var(--bs-body-bg);border-radius:16px;
            box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
    <div class="text-center px-4 py-3">
        <div class="fw-semibold">
            <i class="bi bi-slash-circle me-1"></i><span id="skipSelectedCount">0</span> favori(s) sélectionné(s)
        </div>
    </div>
    <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
        <button type="button" id="btnDeselectSkip"
                class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                style="border-radius:0;border-right:1px solid var(--bs-border-color)">
            Annuler
        </button>
        <button type="button" id="btnSkipSelected"
                class="btn btn-link flex-fill fw-semibold py-2 px-3"
                style="border-radius:0">
            Exclure
        </button>
    </div>
</div>

<!-- Barre revérification en lot -->
<div id="recheckActionsBar" class="d-none"
     style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:min(300px,calc(100vw - 2rem));z-index:1025;
            background:var(--bs-body-bg);border-radius:16px;
            box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
    <div class="text-center px-4 py-3">
        <div class="text-primary fw-semibold">
            <i class="bi bi-arrow-repeat me-1"></i><span id="recheckSelectedCount">0</span> lien(s) à revérifier
        </div>
    </div>
    <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
        <button type="button" id="btnDeselectRecheck"
                class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                style="border-radius:0;border-right:1px solid var(--bs-border-color)">
            Annuler
        </button>
        <button type="button" id="btnRecheckSelected"
                class="btn btn-link flex-fill text-primary fw-semibold py-2 px-3"
                style="border-radius:0">
            Revérifier
        </button>
    </div>
</div>

<!-- Modale confirmation réinitialisation -->
<div class="modal fade" id="resetStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:min(300px,calc(100vw - 2rem))">
        <div class="modal-content border-0" style="border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.18)">
            <div class="text-center px-4 pt-4 pb-3">
                <div class="fw-bold mb-1">Réinitialiser les statuts</div>
                <div class="text-muted small">Les résultats existants seront effacés.</div>
            </div>
            <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
                <button type="button" class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                        style="border-radius:0;border-right:1px solid var(--bs-border-color)"
                        data-bs-dismiss="modal">Annuler</button>
                <button type="button" id="btnResetStatusConfirm"
                        class="btn btn-link flex-fill fw-semibold py-2 px-3"
                        style="border-radius:0">Réinitialiser</button>
            </div>
        </div>
    </div>
</div>

<!-- Modale confirmation suppression en lot -->
<div class="modal fade" id="deleteDeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:min(300px,calc(100vw - 2rem))">
        <div class="modal-content border-0" style="border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.18)">
            <div class="text-center px-4 pt-4 pb-3">
                <div class="fw-bold mb-1">Supprimer la sélection</div>
                <div class="text-muted small">
                    <strong id="deleteDeadModalCount">0</strong> favori(s) supprimé(s) définitivement.
                </div>
            </div>
            <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
                <button type="button" class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                        style="border-radius:0;border-right:1px solid var(--bs-border-color)"
                        data-bs-dismiss="modal">Annuler</button>
                <button type="button" id="btnDeleteDeadConfirm"
                        class="btn btn-link flex-fill text-danger fw-semibold py-2 px-3"
                        style="border-radius:0">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<!-- Section Exclus de la vérification -->
<?php if (!empty($skipped)): ?>
<h6 class="mt-4 mb-2 text-secondary">
    <i class="bi bi-slash-circle me-1"></i>Exclus de la vérification (<?= count($skipped) ?>)
</h6>
<div class="table-responsive mb-3">
    <table class="table table-sm align-middle ks-table">
        <thead class="table-light">
            <tr>
                <th style="width:32px"></th>
                <th style="width:32px"></th>
                <th>Titre / URL</th>
                <th style="width:100px">Dernier statut</th>
                <th style="width:140px">Vérifié le</th>
                <th style="width:68px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($skipped as $bm): ?>
            <tr data-id="<?= $bm['id'] ?>" data-status="skip" data-skip="1">
                <td></td>
                <td></td>
                <td>
                    <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener"
                       class="fw-semibold text-decoration-none text-body">
                        <?= View::e($bm['title'] ?: $bm['host'] ?: $bm['url']) ?>
                    </a>
                    <div class="text-muted small text-truncate" style="max-width:400px">
                        <?= View::e($bm['url']) ?>
                    </div>
                </td>
                <td>
                    <?php if ($bm['last_check_status']): ?>
                    <?php
                        $skippedStatusCls = match($bm['last_check_status']) {
                            'ok'       => 'text-bg-success',
                            'redirect' => 'text-bg-warning',
                            default    => 'text-bg-secondary',
                        };
                        $skippedCode = (int)($bm['last_http_code'] ?? 0);
                        $skippedDisplay = $skippedCode > 0 ? $skippedCode
                            : ($bm['last_check_status'] === 'timeout' ? 'Timeout' : $bm['last_check_status']);
                    ?>
                    <span class="badge <?= $skippedStatusCls ?>"><?= View::e((string)$skippedDisplay) ?></span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small">
                    <?= $bm['last_check_at'] ? View::e(substr($bm['last_check_at'], 0, 16)) : '—' ?>
                </td>
                <td>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary ks-skip-btn p-1 lh-1"
                            data-id="<?= $bm['id'] ?>"
                            data-skip="1"
                            title="Réintégrer à la vérification">
                        <i class="bi bi-arrow-counterclockwise" style="font-size:.85rem"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Barre mise à jour redirects -->
<div id="redirectActionsBar" class="d-none"
     style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
            width:min(300px,calc(100vw - 2rem));z-index:1025;
            background:var(--bs-body-bg);border-radius:16px;
            box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden">
    <div class="text-center px-4 py-3">
        <div class="text-warning-emphasis fw-semibold">
            <i class="bi bi-arrow-right-circle me-1"></i><span id="redirectSelectedCount">0</span> lien(s) redirigé(s)
        </div>
    </div>
    <div class="d-flex" style="border-top:1px solid var(--bs-border-color)">
        <button type="button" id="btnDeselectRedirects"
                class="btn btn-link flex-fill text-secondary fw-normal py-2 px-3"
                style="border-radius:0;border-right:1px solid var(--bs-border-color)">
            Annuler
        </button>
        <button type="button" id="btnUpdateRedirects"
                class="btn btn-link flex-fill text-warning-emphasis fw-semibold py-2 px-3"
                style="border-radius:0">
            Mettre à jour
        </button>
    </div>
</div>
<script src="<?= View::asset('js/links.js') ?>"></script>
