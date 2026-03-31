<?php

use App\Config\BadgeStyles;
use App\Core\View;

// ── URL helper ────────────────────────────────────────────────────────────────
$readOnly = $readOnly ?? false;
$baseAction = $readOnly ? 'home' : 'bookmarks';
$search     = $search ?? '';
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total      = $total ?? count($bookmarks);

$listRaw = $listRaw ?? null;
$showAll = $showAll ?? false;
$q = fn(array $overrides = []): string => '?' . http_build_query(array_merge(
    array_filter([
        'action'   => $baseAction,
        'list'     => $listRaw,
        'tag'      => $tag ?: null,
        'sort'     => $sort !== 'position' ? $sort : null,
        'view'     => $view !== 'badges' ? $view : null,
        'q'        => $search ?: null,
        'page'     => $page > 1 ? $page : null,
        'perpage'  => $showAll ? 'all' : null,
    ], fn($v) => $v !== null && $v !== ''),
    $overrides
));

// Plage de numéros de page à afficher autour de la page courante
$pageRange = function(int $current, int $total, int $delta = 2): array {
    $range = range(max(1, $current - $delta), min($total, $current + $delta));
    if (($range[0] ?? 1) > 2)     array_unshift($range, '…');
    if (($range[0] ?? 1) > 1)     array_unshift($range, 1);
    if (end($range) < $total - 1) $range[] = '…';
    if (end($range) < $total)     $range[] = $total;
    return $range;
};

$badgeStyles = BadgeStyles::all();
?>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show mb-3 text-center" role="alert">
        <?= View::e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── Barre d'outils ────────────────────────────────────────────────────── -->
<div class="ks-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">

    <!-- Switcher de vue -->
    <div class="ks-view-switcher btn-group btn-group-sm">
        <a href="<?= $q(['view' => 'badges', 'page' => null]) ?>"
           class="btn btn-outline-secondary<?= $view === 'badges' ? ' active' : '' ?>"
           title="Vue Badges"><i class="bi bi-grid-3x3-gap-fill"></i></a>
        <a href="<?= $q(['view' => 'table', 'page' => null]) ?>"
           class="btn btn-outline-secondary<?= $view === 'table' ? ' active' : '' ?>"
           title="Vue Tableau"><i class="bi bi-table"></i></a>
        <a href="<?= $q(['view' => 'list', 'page' => null]) ?>"
           class="btn btn-outline-secondary<?= $view === 'list' ? ' active' : '' ?>"
           title="Vue Liste"><i class="bi bi-list-ul"></i></a>
    </div>

    <!-- Tri -->
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-sort-down me-1"></i>Tri
        </button>
        <ul class="dropdown-menu">
            <?php foreach ([
                'position'   => 'Position',
                'title'      => 'Titre A→Z',
                'host'       => 'Domaine A→Z',
                'badge_text' => 'Badge A→Z',
                'date_desc'  => 'Plus récent',
                'date_asc'   => 'Plus ancien',
            ] as $key => $label): ?>
                <li>
                    <a class="dropdown-item<?= $sort === $key ? ' active' : '' ?>"
                       href="<?= $q(['sort' => $key, 'page' => null]) ?>">
                        <?= $label ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Filtre liste -->
    <?php
        $currentListName = null;
        if ($listId !== null) {
            foreach ($lists as $l) {
                if ((int) $l['id'] === $listId) { $currentListName = $l['name']; break; }
            }
        }
        // "Toutes" explicitement choisi (list=0) ou aucune liste par défaut
        $listExplicitlyChosen = $listRaw !== null;
        $listLabel = $currentListName !== null
            ? View::e($currentListName)
            : ($listExplicitlyChosen ? '— Toutes' : 'Liste');
    ?>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle<?= $listExplicitlyChosen ? ' text-primary fw-semibold' : '' ?>"
                data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="bi bi-collection me-1"></i><?= $listLabel ?>
        </button>
        <div class="dropdown-menu p-2" style="min-width:200px">
            <input type="search" class="form-control form-control-sm mb-1 ks-list-search"
                   placeholder="Rechercher…" autocomplete="off">
            <div class="ks-list-dropdown-items">
                <a class="dropdown-item<?= $listId === null ? ' active' : '' ?>"
                   href="<?= $q(['list' => 0, 'tag' => null, 'page' => null]) ?>">— Toutes</a>
                <hr class="dropdown-divider my-1">
                <?php foreach ($lists as $l): ?>
                    <a class="dropdown-item<?= $listId === (int) $l['id'] ? ' active' : '' ?>"
                       href="<?= $q(['list' => $l['id'], 'tag' => null, 'page' => null]) ?>"
                       data-list-name="<?= strtolower(View::e($l['name'])) ?>">
                        <?= View::e($l['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filtre tag -->
    <?php if (!empty($allTags)): ?>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle<?= $tag ? ' text-primary fw-semibold' : '' ?>"
                data-bs-toggle="dropdown">
            <i class="bi bi-tag me-1"></i><?= $tag ? View::e($tag) : 'Tag' ?>
        </button>
        <ul class="dropdown-menu" style="max-height:260px;overflow-y:auto">
            <?php if ($tag): ?>
                <li><a class="dropdown-item" href="<?= $q(['tag' => null]) ?>">— Tous</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <?php foreach ($allTags as $t): ?>
                <li>
                    <a class="dropdown-item<?= $tag === $t ? ' active' : '' ?>"
                       href="<?= $q(['tag' => $t, 'page' => null]) ?>">
                        <?= View::e($t) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Taille des badges (vue badges uniquement) -->
    <?php if ($view === 'badges'): ?>
    <div class="btn-group btn-group-sm">
        <button id="btnBadgeSmaller" class="btn btn-outline-secondary" title="Réduire les badges">
            <i class="bi bi-dash-lg"></i>
        </button>
        <span class="btn btn-outline-secondary pe-none" id="badgeSizeLabel"
              style="min-width:2.4rem;cursor:default"></span>
        <button id="btnBadgeLarger" class="btn btn-outline-secondary" title="Agrandir les badges">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Recherche -->
    <form class="ks-search-form" method="get">
        <input type="hidden" name="action" value="<?= $baseAction ?>">
        <?php if ($listRaw !== null): ?><input type="hidden" name="list" value="<?= $listRaw ?>"><?php endif; ?>
        <?php if ($view !== 'badges'): ?><input type="hidden" name="view" value="<?= View::e($view) ?>"><?php endif; ?>
        <?php if ($sort !== 'position'): ?><input type="hidden" name="sort" value="<?= View::e($sort) ?>"><?php endif; ?>
        <?php if ($tag): ?><input type="hidden" name="tag" value="<?= View::e($tag) ?>"><?php endif; ?>
        <?php if ($showAll): ?><input type="hidden" name="perpage" value="all"><?php endif; ?>
        <div class="ks-search-wrap">
            <i class="bi bi-search ks-search-icon"></i>
            <input type="search" name="q" class="ks-search-input<?= $search ? ' active' : '' ?>"
                   placeholder="Rechercher…" value="<?= View::e($search) ?>"
                   autocomplete="off">
        </div>
    </form>

    <!-- Compteur -->
    <?php
        $displayed = count($bookmarks);
        $showRatio = !$showAll && $total > $displayed;
        $fav       = fn(int $n): string => $n . ' favori' . ($n > 1 ? 's' : '');
    ?>
    <?php if ($search): ?>
    <span class="text-muted small ms-1">
        <?= $showRatio ? $fav($displayed) . ' / ' . $fav($total) : $fav($total) ?> pour
        <em>«&nbsp;<?= View::e($search) ?>&nbsp;»</em>
        — <a href="<?= $q(['q' => null]) ?>" class="text-muted">effacer</a>
    </span>
    <?php else: ?>
    <span class="text-muted small ms-1">
        <?= $showRatio ? $fav($displayed) . ' / ' . $fav($total) : $fav($total) ?>
    </span>
    <?php endif; ?>

    <?php if (!$readOnly): ?>
    <button class="btn btn-sm btn-primary ms-auto"
            data-bs-toggle="modal" data-bs-target="#bookmarkModal"
            data-mode="add">
        <i class="bi bi-plus-lg me-1"></i>Ajouter
    </button>
    <?php endif; ?>
</div>

<!-- ── Vue Badges ─────────────────────────────────────────────────────────── -->
<?php if ($view === 'badges'): ?>

<div class="ks-badges-grid">
    <?php if (empty($bookmarks)): ?>
        <p class="text-muted">Aucun favori.</p>
    <?php endif; ?>
    <?php foreach ($bookmarks as $bm): ?>
        <?php $bg = BadgeStyles::gradient($bm['badge_style']); ?>
        <div class="ks-badge" data-id="<?= $bm['id'] ?>">
            <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener" class="ks-badge-link">
                <div class="ks-badge-thumb" style="background:<?= $bg ?>">
                    <span><?= View::e($bm['badge_text'] ?: $bm['title'] ?: $bm['host']) ?></span>
                </div>
            </a>
            <div class="ks-badge-footer">
                <?php if (!$readOnly && $sort === 'position'): ?>
                <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                <?php endif; ?>
                <span class="ks-badge-host"><?= View::e($bm['host']) ?></span>
                <?php if (!$readOnly): ?>
                <button class="ks-badge-edit btn btn-link p-0"
                        data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                        data-mode="edit"
                        data-id="<?= $bm['id'] ?>"
                        data-url="<?= View::e($bm['url']) ?>"
                        data-host="<?= View::e($bm['host']) ?>"
                        data-title="<?= View::e($bm['title']) ?>"
                        data-description="<?= View::e($bm['description']) ?>"
                        data-badge-style="<?= View::e($bm['badge_style']) ?>"
                        data-badge-text="<?= View::e($bm['badge_text']) ?>"
                        data-tags="<?= View::e($bm['tags']) ?>"
                        data-visibility="<?= View::e($bm['visibility']) ?>"
                        data-list-id="<?= (int) $bm['list_id'] ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ── Vue Tableau ────────────────────────────────────────────────────────── -->
<?php elseif ($view === 'table'): ?>

<div class="table-responsive">
    <table class="table table-hover table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:36px"></th>
                <th>Titre / Domaine</th>
                <th>Liste</th>
                <th>Tags</th>
                <th>Visibilité</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($bookmarks)): ?>
            <tr><td colspan="6" class="text-muted text-center py-3">Aucun favori.</td></tr>
        <?php endif; ?>
        <?php foreach ($bookmarks as $bm): ?>
            <?php $bg = BadgeStyles::gradient($bm['badge_style']); ?>
            <tr data-id="<?= $bm['id'] ?>">
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <?php if (!$readOnly && $sort === 'position'): ?>
                        <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                        <?php endif; ?>
                        <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener">
                            <div class="ks-table-thumb" style="background:<?= $bg ?>"></div>
                        </a>
                    </div>
                </td>
                <td>
                    <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener"
                       class="fw-semibold text-decoration-none text-body">
                        <?= View::e($bm['title'] ?: $bm['host']) ?>
                    </a>
                    <div class="text-muted small"><?= View::e($bm['host']) ?></div>
                </td>
                <td class="text-muted small"><?= View::e($bm['list_name'] ?? '') ?></td>
                <td>
                    <?php foreach (array_filter(explode(',', $bm['tags'] ?? '')) as $t): ?>
                        <a href="<?= $q(['tag' => trim($t)]) ?>"
                           class="badge text-bg-secondary text-decoration-none me-1">
                            <?= View::e(trim($t)) ?>
                        </a>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php if ($bm['visibility'] === 'public'): ?>
                        <span class="badge text-bg-success">Public</span>
                    <?php else: ?>
                        <span class="badge text-bg-light text-muted border">Privé</span>
                    <?php endif; ?>
                </td>
                <?php if (!$readOnly): ?>
                <td>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                            data-mode="edit"
                            data-id="<?= $bm['id'] ?>"
                            data-url="<?= View::e($bm['url']) ?>"
                            data-host="<?= View::e($bm['host']) ?>"
                            data-title="<?= View::e($bm['title']) ?>"
                            data-description="<?= View::e($bm['description']) ?>"
                            data-badge-style="<?= View::e($bm['badge_style']) ?>"
                            data-badge-text="<?= View::e($bm['badge_text']) ?>"
                            data-tags="<?= View::e($bm['tags']) ?>"
                            data-visibility="<?= View::e($bm['visibility']) ?>"
                            data-list-id="<?= (int) $bm['list_id'] ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── Vue Liste compacte ─────────────────────────────────────────────────── -->
<?php else: ?>

<div class="ks-compact-list">
    <?php if (empty($bookmarks)): ?>
        <p class="text-muted">Aucun favori.</p>
    <?php endif; ?>
    <?php foreach ($bookmarks as $bm): ?>
        <?php $bg = BadgeStyles::gradient($bm['badge_style']); ?>
        <div class="ks-compact-item" data-id="<?= $bm['id'] ?>">
            <?php if (!$readOnly && $sort === 'position'): ?>
            <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
            <?php endif; ?>
            <div class="ks-compact-dot" style="background:<?= $bg ?>"></div>
            <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener"
               class="ks-compact-title text-decoration-none text-body fw-semibold">
                <?= View::e($bm['title'] ?: $bm['host']) ?>
            </a>
            <span class="ks-compact-host text-muted small"><?= View::e($bm['host']) ?></span>
            <div class="ks-compact-tags">
                <?php foreach (array_filter(explode(',', $bm['tags'] ?? '')) as $t): ?>
                    <a href="<?= $q(['tag' => trim($t)]) ?>"
                       class="badge text-bg-secondary text-decoration-none">
                        <?= View::e(trim($t)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!$readOnly): ?>
            <button class="ks-compact-edit btn btn-link p-0 ms-auto"
                    data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                    data-mode="edit"
                    data-id="<?= $bm['id'] ?>"
                    data-url="<?= View::e($bm['url']) ?>"
                    data-host="<?= View::e($bm['host']) ?>"
                    data-title="<?= View::e($bm['title']) ?>"
                    data-description="<?= View::e($bm['description']) ?>"
                    data-badge-style="<?= View::e($bm['badge_style']) ?>"
                    data-badge-text="<?= View::e($bm['badge_text']) ?>"
                    data-tags="<?= View::e($bm['tags']) ?>"
                    data-visibility="<?= View::e($bm['visibility']) ?>"
                    data-list-id="<?= (int) $bm['list_id'] ?>">
                <i class="bi bi-pencil text-secondary"></i>
            </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<?php if ($totalPages > 1 || $showAll): ?>
<nav class="ks-pagination" aria-label="Pagination">

    <?php if (!$showAll): ?>
    <a class="ks-page-btn<?= $page <= 1 ? ' disabled' : '' ?>"
       href="<?= $page > 1 ? $q(['page' => $page - 1]) : '#' ?>">
        <i class="bi bi-chevron-left"></i>
    </a>

    <?php foreach ($pageRange($page, $totalPages) as $p): ?>
        <?php if ($p === '…'): ?>
            <span class="ks-page-ellipsis">…</span>
        <?php else: ?>
            <a class="ks-page-btn<?= $p === $page ? ' active' : '' ?>"
               href="<?= $q(['page' => $p]) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <a class="ks-page-btn<?= $page >= $totalPages ? ' disabled' : '' ?>"
       href="<?= $page < $totalPages ? $q(['page' => $page + 1]) : '#' ?>">
        <i class="bi bi-chevron-right"></i>
    </a>
    <?php endif; ?>

    <a class="ks-page-btn<?= $showAll ? ' active' : '' ?>"
       href="<?= $showAll ? $q(['perpage' => null, 'page' => null]) : $q(['perpage' => 'all', 'page' => null]) ?>"
       title="<?= $showAll ? 'Revenir à la pagination' : 'Tout afficher' ?>">
        <i class="bi bi-<?= $showAll ? 'grid' : 'infinity' ?>"></i>
    </a>

</nav>
<?php endif; ?>

<?php if (!$readOnly): ?>
<!-- ── Modal Ajout / Édition ─────────────────────────────────────────────── -->
<div class="modal fade ks-modal" id="bookmarkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookmarkModalTitle">Ajouter un favori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="post" id="bookmarkForm">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="id" id="bmId">
                <input type="hidden" name="_list_id" value="<?= $listId ?? '' ?>">
                <input type="hidden" name="_view" value="<?= View::e($view) ?>">

                <div class="modal-body">

                    <!-- URL -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL</label>
                        <div class="input-group">
                            <input type="url" class="form-control" name="url" id="bmUrl"
                                   placeholder="https://…" required>
                            <button type="button" class="btn btn-outline-secondary" id="btnFetchMeta">
                                <i class="bi bi-magic me-1"></i>Fetch
                            </button>
                        </div>
                        <div id="fetchSpinner" class="text-muted small mt-1 d-none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Récupération…
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Titre -->
                        <div class="col-md-8">
                            <label class="form-label">Titre</label>
                            <input type="text" class="form-control" name="title" id="bmTitle">
                        </div>
                        <!-- Host -->
                        <div class="col-md-4">
                            <label class="form-label">Domaine</label>
                            <input type="text" class="form-control" name="host" id="bmHost"
                                   placeholder="example.com">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="bmDescription"
                                  rows="2"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Liste -->
                        <div class="col-md-5">
                            <label class="form-label">Liste</label>
                            <select class="d-none" name="list_id" id="bmListId">
                                <option value="">— Aucune —</option>
                                <?php foreach ($lists as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= View::e($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="dropdown w-100" id="bmListDropdown">
                                <button type="button"
                                        class="form-select text-start d-flex align-items-center justify-content-between"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        id="bmListBtn">
                                    <span id="bmListLabel">— Aucune —</span>
                                    <i class="bi bi-chevron-down ms-2 text-muted" style="font-size:.75rem"></i>
                                </button>
                                <div class="dropdown-menu w-100 p-2">
                                    <input type="search" class="form-control form-control-sm mb-1"
                                           id="bmListSearch" placeholder="Rechercher…" autocomplete="off">
                                    <div style="max-height:200px;overflow-y:auto" id="bmListItems">
                                        <a class="dropdown-item" href="#" data-value="">— Aucune —</a>
                                        <?php foreach ($lists as $l): ?>
                                            <a class="dropdown-item" href="#"
                                               data-value="<?= $l['id'] ?>"
                                               data-label="<?= View::e($l['name']) ?>">
                                                <?= View::e($l['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Nouvelle liste -->
                        <div class="col-md-4">
                            <label class="form-label">Nouvelle liste</label>
                            <input type="text" class="form-control" name="new_list"
                                   placeholder="Nom de la liste">
                        </div>
                        <!-- Visibilité -->
                        <div class="col-md-3">
                            <label class="form-label">Visibilité</label>
                            <select class="form-select" name="visibility" id="bmVisibility">
                                <option value="private">Privé</option>
                                <option value="public">Public</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Texte du badge -->
                        <div class="col-md-6">
                            <label class="form-label">Texte du badge</label>
                            <input type="text" class="form-control" name="badge_text" id="bmBadgeText"
                                   placeholder="Affiché sur la carte">
                        </div>
                        <!-- Tags -->
                        <div class="col-md-6">
                            <label class="form-label">Tags <small class="text-muted">(séparés par virgule)</small></label>
                            <input type="text" class="form-control" name="tags" id="bmTags"
                                   placeholder="php, dev, tools">
                        </div>
                    </div>

                    <!-- Style de badge -->
                    <div class="mb-2">
                        <label class="form-label">Couleur du badge</label>
                        <div class="ks-badge-picker d-flex flex-wrap gap-2">
                            <?php foreach ($badgeStyles as $key => $style): ?>
                                <label class="ks-color-swatch" title="<?= View::e($style['label']) ?>">
                                    <input type="radio" name="badge_style" value="<?= $key ?>"
                                           class="d-none ks-badge-style-radio">
                                    <span class="ks-color-dot" style="background:<?= $style['bg'] ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /modal-body -->

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnDelete">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="bmSubmitBtn">Ajouter</button>
                    </div>
                </div>
            </form>

            <!-- Formulaire de suppression (séparé pour éviter le submit croisé) -->
            <form method="post" action="?action=bookmark_delete" id="deleteForm" class="d-none">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="id" id="deleteId">
                <input type="hidden" name="_list_id" value="<?= $listId ?? '' ?>">
                <input type="hidden" name="_view" value="<?= View::e($view) ?>">
            </form>

        </div>
    </div>
</div>

<!-- ── JS recherche liste ─────────────────────────────────────────────────── -->
<script>
(function () {
    const input = document.querySelector('.ks-list-search');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.ks-list-dropdown-items a[data-list-name]').forEach(a => {
            a.style.display = a.dataset.listName.includes(q) ? '' : 'none';
        });
    });
    // Garder le focus dans l'input sans fermer le dropdown
    input.addEventListener('click', e => e.stopPropagation());
})();
</script>

<!-- ── JS modal ───────────────────────────────────────────────────────────── -->
<script>
(function () {
    const modal     = document.getElementById('bookmarkModal');
    const form      = document.getElementById('bookmarkForm');
    const deleteForm = document.getElementById('deleteForm');

    // Populate modal on show
    modal.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const mode = btn?.dataset.mode ?? 'add';

        document.getElementById('bookmarkModalTitle').textContent =
            mode === 'edit' ? 'Modifier le favori' : 'Ajouter un favori';
        document.getElementById('bmSubmitBtn').textContent =
            mode === 'edit' ? 'Enregistrer' : 'Ajouter';

        form.action = mode === 'edit' ? '?action=bookmark_update' : '?action=bookmark_store';

        document.getElementById('btnDelete').classList.toggle('d-none', mode !== 'edit');

        if (mode === 'edit') {
            document.getElementById('bmId').value          = btn.dataset.id;
            document.getElementById('bmUrl').value         = btn.dataset.url;
            document.getElementById('bmHost').value        = btn.dataset.host;
            document.getElementById('bmTitle').value       = btn.dataset.title;
            document.getElementById('bmDescription').value = btn.dataset.description;
            document.getElementById('bmBadgeText').value   = btn.dataset.badgeText;
            document.getElementById('bmTags').value        = btn.dataset.tags;
            const listItem = document.querySelector(`#bmListItems .dropdown-item[data-value="${btn.dataset.listId}"]`);
            selectList(btn.dataset.listId, listItem?.dataset.label ?? listItem?.textContent.trim());
            document.getElementById('bmVisibility').value  = btn.dataset.visibility;
            document.getElementById('deleteId').value      = btn.dataset.id;
            selectBadgeStyle(btn.dataset.badgeStyle);
        } else {
            form.reset();
            document.getElementById('bmId').value = '';
            selectList('', '— Aucune —');
            selectBadgeStyle('deepBlue');
        }
    });

    // Delete button
    document.getElementById('btnDelete').addEventListener('click', function () {
        if (confirm('Supprimer ce favori ?')) {
            deleteForm.submit();
        }
    });

    // Fetch meta
    document.getElementById('btnFetchMeta').addEventListener('click', async function () {
        const url = document.getElementById('bmUrl').value.trim();
        if (!url) return;

        const spinner = document.getElementById('fetchSpinner');
        spinner.classList.remove('d-none');
        this.disabled = true;

        try {
            const res  = await fetch('?action=bookmark_fetch_meta&url=' + encodeURIComponent(url));
            const data = await res.json();

            if (!data.error) {
                if (data.title)       document.getElementById('bmTitle').value       = data.title;
                if (data.host)        document.getElementById('bmHost').value        = data.host;
                if (data.description) document.getElementById('bmDescription').value = data.description;
                if (!document.getElementById('bmBadgeText').value && data.title) {
                    document.getElementById('bmBadgeText').value = data.title.substring(0, 30);
                }
            }
        } catch (err) {
            console.error(err);
        } finally {
            spinner.classList.add('d-none');
            this.disabled = false;
        }
    });

    // Liste picker
    function selectList(value, label) {
        document.getElementById('bmListId').value  = value;
        document.getElementById('bmListLabel').textContent = label || '— Aucune —';
        // Marquer l'item actif
        document.querySelectorAll('#bmListItems .dropdown-item').forEach(a => {
            a.classList.toggle('active', a.dataset.value === String(value));
        });
        bootstrap.Dropdown.getInstance(document.getElementById('bmListBtn'))?.hide();
    }

    document.getElementById('bmListItems').addEventListener('click', function (e) {
        const item = e.target.closest('.dropdown-item');
        if (!item) return;
        e.preventDefault();
        selectList(item.dataset.value, item.dataset.label ?? item.textContent.trim());
    });

    document.getElementById('bmListSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#bmListItems .dropdown-item').forEach(a => {
            a.style.display = (a.dataset.label ?? a.textContent).toLowerCase().includes(q) ? '' : 'none';
        });
    });

    document.getElementById('bmListSearch').addEventListener('click', e => e.stopPropagation());

    // Badge style picker
    function selectBadgeStyle(style) {
        document.querySelectorAll('.ks-badge-style-radio').forEach(radio => {
            const swatch = radio.closest('.ks-color-swatch');
            radio.checked = radio.value === style;
            swatch.classList.toggle('selected', radio.checked);
        });
    }

    document.querySelectorAll('.ks-badge-style-radio').forEach(radio => {
        radio.addEventListener('change', () => selectBadgeStyle(radio.value));
    });
})();
</script>

<?php if ($sort === 'position'): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
(function () {
    const csrf = <?= json_encode($csrf) ?>;

    async function saveOrder(ids) {
        try {
            await fetch('?action=bookmark_reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: csrf, ids }),
            });
        } catch (e) {
            console.error('Erreur sauvegarde ordre :', e);
        }
    }

    function makeSortable(el, childSelector) {
        if (!el) return;
        new Sortable(el, {
            handle: '.ks-drag-handle',
            animation: 150,
            ghostClass: 'ks-sortable-ghost',
            chosenClass: 'ks-sortable-chosen',
            onEnd() {
                const ids = [...el.querySelectorAll(childSelector)]
                    .map(item => parseInt(item.dataset.id, 10))
                    .filter(Boolean);
                saveOrder(ids);
            },
        });
    }

    makeSortable(document.querySelector('.ks-badges-grid'),  '.ks-badge');
    makeSortable(document.querySelector('.ks-compact-list'), '.ks-compact-item');
    makeSortable(document.querySelector('table tbody'),      'tr');
})();
</script>
<?php endif; ?>

<?php endif; ?>

<?php if ($view === 'badges'): ?>
<script>
(function () {
    const SIZES  = [80, 105, 130, 160, 195, 230];
    const LABELS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    const KEY    = 'ks-badge-size';

    const grid       = document.querySelector('.ks-badges-grid');
    const btnSmaller = document.getElementById('btnBadgeSmaller');
    const btnLarger  = document.getElementById('btnBadgeLarger');
    const label      = document.getElementById('badgeSizeLabel');

    const saved = parseInt(localStorage.getItem(KEY) || '160', 10);
    let idx = SIZES.indexOf(saved);
    if (idx === -1) idx = SIZES.findIndex(s => s >= saved) ?? 2;
    idx = Math.max(0, Math.min(SIZES.length - 1, idx));

    function apply() {
        const size = SIZES[idx];
        grid.style.setProperty('--ks-badge-width', size + 'px');
        label.textContent        = LABELS[idx];
        btnSmaller.disabled      = idx === 0;
        btnLarger.disabled       = idx === SIZES.length - 1;
        localStorage.setItem(KEY, size);
    }

    btnSmaller.addEventListener('click', () => { if (idx > 0)               { idx--; apply(); } });
    btnLarger.addEventListener ('click', () => { if (idx < SIZES.length - 1) { idx++; apply(); } });

    apply();
})();
</script>
<?php endif; ?>
