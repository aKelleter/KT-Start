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
<script>document.documentElement.dataset.listNav = localStorage.getItem('ks-list-nav') || '<?= $listNavStyle ?>';</script>
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
          <?php if (!$readOnly): ?>
          <a href="<?= $q(['view' => 'explorer', 'page' => null]) ?>"
              class="btn btn-outline-secondary<?= $view === 'explorer' ? ' active' : '' ?>"
              title="Vue Explorateur"><i class="bi bi-folder2-open"></i></a>
          <?php endif; ?>
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
        $listNavStyle = $listNavStyle ?? 'dropdown';
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
    <button id="btnListNavToggle" class="btn btn-sm btn-outline-secondary" title=""></button>
    <div id="ks-list-filter-dropdown" class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle<?= $listExplicitlyChosen ? ' text-primary fw-semibold' : '' ?>"
                data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="bi bi-collection me-1"></i><?= $listLabel ?>
        </button>
        <div class="dropdown-menu p-2" style="min-width:200px">
            <input type="search" id="ks-list-search" name="ks-list-search" class="form-control form-control-sm mb-1 ks-list-search"
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

    <!-- Nuage de tags (toggle) -->
    <?php if (!empty($allTags)): ?>
    <button class="btn btn-sm btn-outline-secondary<?= $tag ? ' text-primary fw-semibold' : '' ?>"
            type="button" data-bs-toggle="collapse" data-bs-target="#tagCloud"
            aria-expanded="<?= $tag ? 'true' : 'false' ?>">
        <i class="bi bi-tags me-1"></i><?= $tag ? View::e($tag) : 'Tags' ?>
    </button>
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
    <a href="?action=bookmark_links_report" class="btn btn-sm btn-outline-secondary" title="Vérifier les liens">
        <i class="bi bi-link-45deg"></i>
    </a>
    <button class="btn btn-sm btn-primary"
            data-bs-toggle="modal" data-bs-target="#bookmarkModal"
            data-mode="add"
            title="Nouveau favori (Touche N)">
        <i class="bi bi-plus-lg me-1"></i>Ajouter
    </button>
    <?php if ($listId !== null): ?>
    <button class="btn btn-sm btn-outline-secondary" id="btnFolderCreateRoot" title="Nouveau dossier">
        <i class="bi bi-folder-plus me-1"></i>Dossier
    </button>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── Navigation par listes (mode boutons) ──────────────────────────────── -->
<?php if (!empty($lists)): ?>
<div id="ks-list-nav-buttons" class="ks-list-nav-buttons d-flex flex-wrap align-items-center gap-1 mb-3">
    <a href="<?= $q(['list' => 0, 'tag' => null, 'page' => null]) ?>"
       class="btn btn-sm<?= $listId === null ? ' btn-primary' : ' btn-outline-secondary' ?>">
        <i class="bi bi-collection me-1"></i>Toutes
    </a>
    <?php foreach ($lists as $l): ?>
    <a href="<?= $q(['list' => $l['id'], 'tag' => null, 'page' => null]) ?>"
       class="btn btn-sm<?= $listId === (int) $l['id'] ? ' btn-primary' : ' btn-outline-secondary' ?>">
        <?= View::e($l['name']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Nuage de tags ─────────────────────────────────────────────────────── -->
<?php if (!empty($allTags)): ?>
<?php
    $counts = array_values($allTags);
    $minC   = min($counts);
    $maxC   = max($counts);
    $range  = max($maxC - $minC, 1);
?>
<div class="collapse<?= $tag ? ' show' : '' ?>" id="tagCloud">
    <div class="ks-tag-cloud">
        <?php if ($tag): ?>
        <a href="<?= $q(['tag' => null, 'page' => null]) ?>" class="ks-tag-cloud-reset">
            <i class="bi bi-x-lg me-1"></i>Tous les tags
        </a>
        <?php endif; ?>
        <?php foreach ($allTags as $t => $count):
            $size = round(0.78 + ($count - $minC) / $range * 0.82, 2);
        ?>
        <a href="<?= $q(['tag' => $t, 'page' => null]) ?>"
           class="ks-tag-cloud-item<?= $tag === $t ? ' active' : '' ?>"
           style="font-size:<?= $size ?>rem"
           title="<?= $count ?> favori<?= $count > 1 ? 's' : '' ?>">
            <?= View::e($t) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// ── Regroupement par dossier (vues badges / tableau / liste) ─────────────────
$bmByFolder = [];
if (!empty($folders) && $listId !== null) {
    foreach ($bookmarks as $bm) {
        $key = ($bm['folder_id'] !== null) ? (int) $bm['folder_id'] : 0;
        $bmByFolder[$key][] = $bm;
    }
}

// Compte les favoris d'un dossier et de tous ses descendants
$countBmRecursive = function(int $fid) use (&$countBmRecursive, $bmByFolder, $foldersByParent): int {
    $count = count($bmByFolder[$fid] ?? []);
    foreach ($foldersByParent[$fid] ?? [] as $child) {
        $count += $countBmRecursive((int) $child['id']);
    }
    return $count;
};

// Entête de dossier réutilisable dans les 3 vues
$folderHeader = function(array $folder, bool $ro, int $depth = 0) use ($view): void {
    $indent = $depth > 0 ? ' style="padding-left:' . ($depth * 1.5) . 'rem;background:var(--bs-secondary-bg)"' : ' style="background:var(--bs-secondary-bg)"';
    ?>
<div class="ks-folder-section-header d-flex align-items-center gap-2 px-3 py-1 mb-1 mt-3 rounded"<?= $indent ?>>
    <i class="bi bi-folder2-open text-warning"></i>
    <span class="fw-semibold"><?= \App\Core\View::e($folder['name']) ?></span>
    <?php if (!$ro): ?>
    <span class="ms-auto d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-secondary ks-folder-rename py-0"
                data-id="<?= (int) $folder['id'] ?>"
                data-name="<?= \App\Core\View::e($folder['name']) ?>"
                title="Renommer"><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger ks-folder-delete py-0"
                data-id="<?= (int) $folder['id'] ?>"
                data-name="<?= \App\Core\View::e($folder['name']) ?>"
                title="Supprimer"><i class="bi bi-trash"></i></button>
    </span>
    <?php endif; ?>
</div>
<?php };
?>

<!-- ── Vue Badges ─────────────────────────────────────────────────────────── -->
<?php if ($view === 'badges'): ?>

<?php
$renderBadge = function(array $bm) use ($readOnly, $sort, $q): void {
    $bg = \App\Config\BadgeStyles::gradient($bm['badge_style']);
    $bgColor = \App\Config\BadgeStyles::bg($bm['badge_style']);
    ?>
    <div class="ks-badge" data-id="<?= $bm['id'] ?>" style="--ks-badge-color:<?= $bgColor ?>">
        <a href="<?= \App\Core\View::e($bm['url']) ?>" target="_blank" rel="noopener" class="ks-badge-link">
            <div class="ks-badge-thumb" style="background:<?= $bg ?>">
                <span><?= \App\Core\View::e($bm['badge_text'] ?: $bm['title'] ?: $bm['host']) ?></span>
                <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok' && !$bm['check_skip']): ?>
                <span class="ks-link-dot ks-link-dot--<?= \App\Core\View::e($bm['last_check_status']) ?>"
                      title="<?= $bm['last_check_status'] === 'redirect' ? 'Redirigé (301)' : 'Lien inaccessible' ?>"></span>
                <?php endif; ?>
            </div>
        </a>
        <div class="ks-badge-footer">
            <?php if (!$readOnly && $sort === 'position'): ?>
            <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
            <?php endif; ?>
            <span class="ks-badge-host"><?= \App\Core\View::e($bm['host']) ?></span>
            <?php if (!$readOnly): ?>
            <button class="ks-badge-edit btn btn-link p-0"
                    data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                    data-mode="edit"
                    data-id="<?= $bm['id'] ?>"
                    data-url="<?= \App\Core\View::e($bm['url']) ?>"
                    data-host="<?= \App\Core\View::e($bm['host']) ?>"
                    data-title="<?= \App\Core\View::e($bm['title']) ?>"
                    data-description="<?= \App\Core\View::e($bm['description']) ?>"
                    data-badge-style="<?= \App\Core\View::e($bm['badge_style']) ?>"
                    data-badge-text="<?= \App\Core\View::e($bm['badge_text']) ?>"
                    data-tags="<?= \App\Core\View::e($bm['tags']) ?>"
                    data-visibility="<?= \App\Core\View::e($bm['visibility']) ?>"
                    data-list-id="<?= (int) $bm['list_id'] ?>"
                    data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>"
                    data-check-skip="<?= (int) $bm['check_skip'] ?>">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="ks-quick-delete btn btn-link p-0"
                    data-delete-id="<?= $bm['id'] ?>"
                    title="Supprimer">
                <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<?php if (!empty($folders)): ?>
<?php
// Rendu récursif des badges dossiers et de leur contenu
$renderFolderLevel = function(int $parentKey, bool $isRoot) use (&$renderFolderLevel, $foldersByParent, $bmByFolder, $countBmRecursive, $readOnly, $sort, $listId, $renderBadge): void {
    $childFolders = $foldersByParent[$parentKey] ?? [];
    if (empty($childFolders)) return;

    // Ligne de badges dossiers
    $gridId    = $isRoot ? ' id="ksFolderList"' : '';
    $gridClass = $isRoot ? 'ks-badges-grid mb-3' : 'ks-badges-grid ks-sub-folders-row mb-2 mt-2';
    echo '<div' . $gridId . ' class="' . $gridClass . '" data-list-id="' . (int) $listId . '">';
    foreach ($childFolders as $folder):
        $fid     = (int) $folder['id'];
        $bmCount = $countBmRecursive($fid);
        ?>
        <div class="ks-folder-group" data-folder-id="<?= $fid ?>">
            <div class="ks-badge ks-folder-badge" style="--ks-badge-color:#0288D1">
                <div class="ks-badge-thumb ks-folder-thumb collapsed"
                     role="button"
                     title="<?= \App\Core\View::e($folder['name']) ?>"
                     data-bs-toggle="collapse"
                     data-bs-target="#folderBadges<?= $fid ?>"
                     aria-expanded="false"
                     aria-controls="folderBadges<?= $fid ?>">
                    <i class="bi bi-folder2 ks-folder-icon"></i>
                    <span><?= \App\Core\View::e($folder['name']) ?></span>
                    <span class="ks-folder-count" data-folder-count="<?= $fid ?>"><?= $bmCount ?></span>
                    <i class="bi bi-chevron-down ks-folder-chevron"></i>
                </div>
                <div class="ks-badge-footer">
                    <?php if (!$readOnly): ?>
                    <span class="ks-drag-handle flex-shrink-0 text-muted" title="Réorganiser">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <?php endif; ?>
                    <span class="ks-badge-host"><?= \App\Core\View::e($folder['name']) ?></span>
                    <?php if (!$readOnly): ?>
                    <span class="d-flex gap-1 flex-shrink-0">
                        <button type="button" class="ks-folder-create-child btn btn-link p-0 ks-badge-edit"
                                data-id="<?= $fid ?>" data-name="<?= \App\Core\View::e($folder['name']) ?>" title="Nouveau sous-dossier">
                            <i class="bi bi-folder-plus"></i>
                        </button>
                        <button type="button" class="ks-folder-rename btn btn-link p-0 ks-badge-edit"
                                data-id="<?= $fid ?>" data-name="<?= \App\Core\View::e($folder['name']) ?>" title="Renommer">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="ks-folder-delete btn btn-link p-0 ks-badge-edit"
                                data-id="<?= $fid ?>" data-name="<?= \App\Core\View::e($folder['name']) ?>" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    endforeach;
    echo '</div>';

    // Sections collapse (sous-dossiers + favoris directs)
    foreach ($childFolders as $folder):
        $fid          = (int) $folder['id'];
        $hasSubFolders = !empty($foldersByParent[$fid]);
        ?>
        <div class="collapse" id="folderBadges<?= $fid ?>">
            <div class="d-flex align-items-center gap-2 mb-2 mt-1 small text-muted px-1">
                <i class="bi bi-folder2-open text-warning"></i>
                <span class="fw-semibold"><?= \App\Core\View::e($folder['name']) ?></span>
            </div>
            <?php if ($hasSubFolders): ?>
            <div class="ks-folder-nested-content">
                <?php $renderFolderLevel($fid, false); ?>
            </div>
            <?php endif; ?>
            <div class="ks-badges-grid mb-3<?= empty($bmByFolder[$fid]) ? ' ks-drop-target-empty' : '' ?>"
                 data-folder-id="<?= $fid ?>"
                 data-list-id="<?= (int) $listId ?>">
                <?php foreach ($bmByFolder[$fid] ?? [] as $bm): ?><?php $renderBadge($bm); ?><?php endforeach; ?>
            </div>
        </div>
        <?php
    endforeach;
};
$renderFolderLevel(0, true);
?>
    <?php if (!empty($bmByFolder[0])): ?>
    <?php if (!empty($foldersByParent[0])): ?>
    <div class="ks-folder-section-header d-flex align-items-center gap-2 px-3 py-1 mb-1 mt-2 rounded"
         style="background:var(--bs-secondary-bg)">
        <i class="bi bi-inbox text-muted"></i>
        <span class="text-muted fw-semibold">Sans dossier</span>
    </div>
    <?php endif; ?>
    <div class="ks-badges-grid mb-2"
         data-folder-id=""
         data-list-id="<?= (int) $listId ?>">
        <?php foreach ($bmByFolder[0] as $bm): ?><?php $renderBadge($bm); ?><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (empty($bookmarks)): ?><p class="text-muted">Aucun favori.</p><?php endif; ?>
<?php else: ?>
<div class="ks-badges-grid">
    <?php if (empty($bookmarks)): ?><p class="text-muted">Aucun favori.</p><?php endif; ?>
    <?php foreach ($bookmarks as $bm): ?><?php $renderBadge($bm); ?><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Vue Tableau ────────────────────────────────────────────────────────── -->
<?php elseif ($view === 'table'): ?>

<?php
$renderTableRow = function(array $bm, int $depth = 0) use ($readOnly, $sort, $q): void {
    $bg      = \App\Config\BadgeStyles::gradient($bm['badge_style']);
    $tdStyle = $depth > 0
        ? ' style="padding-left:' . ($depth * 1.5 + .25) . 'rem;border-left:3px solid rgba(2,136,209,.22)"'
        : '';
    ?>
    <tr data-id="<?= $bm['id'] ?>"<?= $depth > 0 ? ' data-depth="' . $depth . '"' : '' ?>>
        <td<?= $tdStyle ?>>
            <div class="d-flex align-items-center gap-1">
                <?php if (!$readOnly && $sort === 'position'): ?>
                <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                <?php endif; ?>
                <a href="<?= \App\Core\View::e($bm['url']) ?>" target="_blank" rel="noopener">
                    <div class="ks-table-thumb" style="background:<?= $bg ?>"></div>
                </a>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok' && !$bm['check_skip']): ?>
                <span class="ks-link-dot ks-link-dot--<?= \App\Core\View::e($bm['last_check_status']) ?> flex-shrink-0"
                      title="<?= $bm['last_check_status'] === 'redirect' ? 'Redirigé (301)' : 'Lien inaccessible' ?>"></span>
                <?php endif; ?>
                <div>
                    <a href="<?= \App\Core\View::e($bm['url']) ?>" target="_blank" rel="noopener"
                       class="fw-semibold text-decoration-none text-body">
                        <?= \App\Core\View::e($bm['title'] ?: $bm['host']) ?>
                    </a>
                    <div class="text-muted small"><?= \App\Core\View::e($bm['host']) ?></div>
                </div>
            </div>
        </td>
        <td class="text-muted small"><?= \App\Core\View::e($bm['list_name'] ?? '') ?></td>
        <td>
            <?php foreach (array_filter(explode(',', $bm['tags'] ?? '')) as $t): ?>
                <a href="<?= $q(['tag' => trim($t)]) ?>"
                   class="badge text-bg-secondary text-decoration-none me-1">
                    <?= \App\Core\View::e(trim($t)) ?>
                </a>
            <?php endforeach; ?>
        </td>
        <td>
            <?php if ($bm['visibility'] === 'public'): ?>
                <span class="badge text-bg-success">Public</span>
            <?php else: ?>
                <span class="badge ks-badge-private">Privé</span>
            <?php endif; ?>
        </td>
        <?php if (!$readOnly): ?>
        <td>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                        data-mode="edit"
                        data-id="<?= $bm['id'] ?>"
                        data-url="<?= \App\Core\View::e($bm['url']) ?>"
                        data-host="<?= \App\Core\View::e($bm['host']) ?>"
                        data-title="<?= \App\Core\View::e($bm['title']) ?>"
                        data-description="<?= \App\Core\View::e($bm['description']) ?>"
                        data-badge-style="<?= \App\Core\View::e($bm['badge_style']) ?>"
                        data-badge-text="<?= \App\Core\View::e($bm['badge_text']) ?>"
                        data-tags="<?= \App\Core\View::e($bm['tags']) ?>"
                        data-visibility="<?= \App\Core\View::e($bm['visibility']) ?>"
                        data-list-id="<?= (int) $bm['list_id'] ?>"
                        data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>"
                        data-check-skip="<?= (int) $bm['check_skip'] ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="ks-quick-delete btn btn-sm btn-outline-secondary"
                        data-delete-id="<?= $bm['id'] ?>"
                        title="Supprimer">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </td>
        <?php endif; ?>
    </tr>
    <?php
};
$tableFolderRow = function(array $folder, bool $ro, int $cols, int $depth = 0) use ($view): void {
    $pad         = $depth > 0 ? 'padding-left:' . ($depth * 1.5 + 0.5) . 'rem' : 'padding-left:.5rem';
    $borderLeft  = $depth > 0 ? 'border-left:3px solid rgba(2,136,209,.28);' : '';
    ?>
    <tr class="ks-table-folder-row">
        <td colspan="<?= $cols ?>" class="py-1 px-2" style="background:var(--bs-secondary-bg);<?= $borderLeft ?><?= $pad ?>">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-folder2-open text-warning"></i>
                <span class="fw-semibold"><?= \App\Core\View::e($folder['name']) ?></span>
                <?php if (!$ro): ?>
                <span class="ms-auto d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary ks-folder-rename py-0"
                            data-id="<?= (int) $folder['id'] ?>"
                            data-name="<?= \App\Core\View::e($folder['name']) ?>"
                            title="Renommer"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger ks-folder-delete py-0"
                            data-id="<?= (int) $folder['id'] ?>"
                            data-name="<?= \App\Core\View::e($folder['name']) ?>"
                            title="Supprimer"><i class="bi bi-trash"></i></button>
                </span>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php
};
$cols = $readOnly ? 5 : 6;
?>

<?php
// Helper : génère un lien de tri pour un en-tête de colonne
$thSort = function(string $label, string $sortAsc, string $sortDesc) use ($sort, $q): string {
    if ($sort === $sortAsc) {
        $icon = '<i class="bi bi-chevron-up ms-1 text-primary" style="font-size:.7rem"></i>';
        $href = $q(['sort' => $sortDesc, 'page' => null]);
        $cls  = 'text-primary';
    } elseif ($sort === $sortDesc) {
        $icon = '<i class="bi bi-chevron-down ms-1 text-primary" style="font-size:.7rem"></i>';
        $href = $q(['sort' => $sortAsc, 'page' => null]);
        $cls  = 'text-primary';
    } else {
        $icon = '<i class="bi bi-chevron-expand ms-1 text-muted" style="font-size:.7rem"></i>';
        $href = $q(['sort' => $sortAsc, 'page' => null]);
        $cls  = '';
    }
    return '<a href="' . $href . '" class="ks-th-sort-link ' . $cls . '">' . $label . $icon . '</a>';
};
?>

<div class="table-responsive">
    <table class="table table-hover table-sm align-middle ks-table">
        <thead class="table-light">
            <tr>
                <th style="width:36px"></th>
                <th><?= $thSort('Titre / Domaine', 'title', 'title_desc') ?></th>
                <th><?= $thSort('Liste', 'list_asc', 'list_desc') ?></th>
                <th>Tags</th>
                <th><?= $thSort('Visibilité', 'visibility_asc', 'visibility_desc') ?></th>
                <?php if (!$readOnly): ?><th style="width:88px"></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($bookmarks)): ?>
            <tr><td colspan="<?= $cols ?>" class="text-muted text-center py-3">Aucun favori.</td></tr>
        <?php elseif (!empty($bmByFolder)): ?>
            <?php
            $renderTableFolderRows = function(int $parentKey, int $depth) use (&$renderTableFolderRows, $foldersByParent, $bmByFolder, $tableFolderRow, $renderTableRow, $readOnly, $cols): void {
                foreach ($foldersByParent[$parentKey] ?? [] as $folder) {
                    $fid = (int) $folder['id'];
                    $tableFolderRow($folder, $readOnly, $cols, $depth);
                    foreach ($bmByFolder[$fid] ?? [] as $bm) { $renderTableRow($bm, $depth); }
                    $renderTableFolderRows($fid, $depth + 1);
                }
            };
            $renderTableFolderRows(0, 0);
            ?>
            <?php if (!empty($bmByFolder[0])): ?>
                <?php if (!empty($foldersByParent[0])): ?>
                <tr class="ks-table-folder-row">
                    <td colspan="<?= $cols ?>" class="py-1 px-2" style="background:var(--bs-secondary-bg)">
                        <i class="bi bi-inbox text-muted me-2"></i>
                        <span class="text-muted fw-semibold">Sans dossier</span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach ($bmByFolder[0] as $bm): ?><?php $renderTableRow($bm); ?><?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php foreach ($bookmarks as $bm): ?><?php $renderTableRow($bm); ?><?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Vue Liste compacte ─────────────────────────────────────────────────── -->
<?php elseif ($view === 'list'): ?>

<?php
$renderListItem = function(array $bm) use ($readOnly, $sort, $q): void {
    $bg = \App\Config\BadgeStyles::gradient($bm['badge_style']); ?>
    <div class="ks-compact-item" data-id="<?= $bm['id'] ?>">
        <?php if (!$readOnly && $sort === 'position'): ?>
        <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
        <?php endif; ?>
        <div class="ks-compact-dot" style="background:<?= $bg ?>"></div>
        <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok' && !$bm['check_skip']): ?>
        <span class="ks-link-dot ks-link-dot--<?= \App\Core\View::e($bm['last_check_status']) ?>"
              title="<?= $bm['last_check_status'] === 'redirect' ? 'Redirigé (301)' : 'Lien inaccessible' ?>"></span>
        <?php endif; ?>
        <a href="<?= \App\Core\View::e($bm['url']) ?>" target="_blank" rel="noopener"
           class="ks-compact-title text-decoration-none text-body fw-semibold">
            <?= \App\Core\View::e($bm['title'] ?: $bm['host']) ?>
        </a>
        <span class="ks-compact-host text-muted small"><?= \App\Core\View::e($bm['host']) ?></span>
        <div class="ks-compact-tags">
            <?php foreach (array_filter(explode(',', $bm['tags'] ?? '')) as $t): ?>
                <a href="<?= $q(['tag' => trim($t)]) ?>"
                   class="badge text-bg-secondary text-decoration-none">
                    <?= \App\Core\View::e(trim($t)) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$readOnly): ?>
        <button class="ks-compact-edit btn btn-link p-0 ms-auto"
                data-bs-toggle="modal" data-bs-target="#bookmarkModal"
                data-mode="edit"
                data-id="<?= $bm['id'] ?>"
                data-url="<?= \App\Core\View::e($bm['url']) ?>"
                data-host="<?= \App\Core\View::e($bm['host']) ?>"
                data-title="<?= \App\Core\View::e($bm['title']) ?>"
                data-description="<?= \App\Core\View::e($bm['description']) ?>"
                data-badge-style="<?= \App\Core\View::e($bm['badge_style']) ?>"
                data-badge-text="<?= \App\Core\View::e($bm['badge_text']) ?>"
                data-tags="<?= \App\Core\View::e($bm['tags']) ?>"
                data-visibility="<?= \App\Core\View::e($bm['visibility']) ?>"
                data-list-id="<?= (int) $bm['list_id'] ?>"
                data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>"
                data-check-skip="<?= (int) $bm['check_skip'] ?>">
            <i class="bi bi-pencil text-secondary"></i>
        </button>
        <button class="ks-quick-delete btn btn-link p-0"
                data-delete-id="<?= $bm['id'] ?>"
                title="Supprimer">
            <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
    </div>
    <?php
};
?>

<?php if (empty($bookmarks)): ?>
    <p class="text-muted">Aucun favori.</p>
<?php elseif (!empty($bmByFolder)): ?>
    <?php
    $renderListFolderSections = function(int $parentKey, int $depth) use (&$renderListFolderSections, $foldersByParent, $bmByFolder, $folderHeader, $renderListItem, $readOnly): void {
        foreach ($foldersByParent[$parentKey] ?? [] as $folder) {
            $fid           = (int) $folder['id'];
            $hasSubFolders = !empty($foldersByParent[$fid]);
            $folderHeader($folder, $readOnly, 0); // le wrapper gère l'indentation
            if (!empty($bmByFolder[$fid])) {
                echo '<div class="ks-compact-list mb-2" data-folder-id="' . $fid . '" data-list-id="' . (int)$listId . '">';
                foreach ($bmByFolder[$fid] as $bm) { $renderListItem($bm); }
                echo '</div>';
            }
            if ($hasSubFolders) {
                echo '<div class="ks-folder-nested-content">';
                $renderListFolderSections($fid, $depth + 1);
                echo '</div>';
            }
        }
    };
    $renderListFolderSections(0, 0);
    ?>
    <?php if (!empty($bmByFolder[0])): ?>
        <?php if (!empty($foldersByParent[0])): ?>
        <div class="ks-folder-section-header d-flex align-items-center gap-2 px-3 py-1 mb-1 mt-2 rounded"
             style="background:var(--bs-secondary-bg)">
            <i class="bi bi-inbox text-muted"></i>
            <span class="text-muted fw-semibold">Sans dossier</span>
        </div>
        <?php endif; ?>
        <div class="ks-compact-list mb-2" data-folder-id="" data-list-id="<?= (int) $listId ?>">
            <?php foreach ($bmByFolder[0] as $bm): ?><?php $renderListItem($bm); ?><?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
<div class="ks-compact-list">
    <?php foreach ($bookmarks as $bm): ?><?php $renderListItem($bm); ?><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Vue Explorateur ────────────────────────────────────────────────────── -->
<?php elseif ($view === 'explorer' && !$readOnly): ?>

<?php if ($listId === null): ?>
    <div class="alert alert-info mb-0">Sélectionnez une liste pour utiliser l'explorateur de dossiers.</div>
<?php else: ?>
    <?php
    $countExplorerRecursive = function(int $fid) use (&$countExplorerRecursive, $bookmarksByFolder, $foldersByParent): int {
        $count = count($bookmarksByFolder[$fid] ?? []);
        foreach ($foldersByParent[$fid] ?? [] as $child) {
            $count += $countExplorerRecursive((int) $child['id']);
        }
        return $count;
    };

    $renderExplorerNodes = function (?int $parentId = null, bool $collapsed = false) use (&$renderExplorerNodes, $foldersByParent, $bookmarksByFolder, $countExplorerRecursive, $q): void {
        $parentKey = $parentId ?? 0;
        $childFolders = $foldersByParent[$parentKey] ?? [];
        $childBookmarks = $bookmarksByFolder[$parentKey] ?? [];

        $nodes = [];
        foreach ($childFolders as $folder) {
            $nodes[] = ['type' => 'folder', 'position' => (int) $folder['position'], 'data' => $folder];
        }
        foreach ($childBookmarks as $bookmark) {
            $nodes[] = ['type' => 'bookmark', 'position' => (int) $bookmark['position'], 'data' => $bookmark];
        }

        usort($nodes, function($a, $b) {
            // Dossiers toujours avant les favoris, puis tri par position dans chaque groupe
            $typeOrder = ['folder' => 0, 'bookmark' => 1];
            $t = ($typeOrder[$a['type']] ?? 1) <=> ($typeOrder[$b['type']] ?? 1);
            return $t !== 0 ? $t : $a['position'] <=> $b['position'];
        });
        ?>
        <div class="ks-explorer-dropzone<?= $collapsed ? ' d-none' : '' ?>" data-parent-id="<?= $parentId ?? '' ?>">
            <?php foreach ($nodes as $node): ?>
                <?php if ($node['type'] === 'folder'): ?>
                    <?php $folder = $node['data']; ?>
                    <div class="ks-explorer-node ks-explorer-folder" data-type="folder" data-id="<?= (int) $folder['id'] ?>">
                        <div class="ks-explorer-row">
                            <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                            <button type="button" class="btn btn-link p-0 text-decoration-none ks-folder-toggle" data-folder-id="<?= (int) $folder['id'] ?>" aria-expanded="false">
                                <i class="bi bi-caret-right-fill"></i>
                            </button>
                            <i class="bi bi-folder2 text-warning"></i>
                            <span class="fw-semibold"><?= View::e($folder['name']) ?></span>
                            <?php $folderBmCount = $countExplorerRecursive((int) $folder['id']); ?>
                            <?php if ($folderBmCount > 0): ?>
                            <span class="badge text-bg-secondary ms-1"><?= $folderBmCount ?></span>
                            <?php endif; ?>
                            <span class="ms-auto d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary ks-folder-create-child" data-id="<?= (int) $folder['id'] ?>" data-name="<?= View::e($folder['name']) ?>" title="Nouveau sous-dossier"><i class="bi bi-folder-plus"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ks-folder-rename" data-id="<?= (int) $folder['id'] ?>" data-name="<?= View::e($folder['name']) ?>" title="Renommer"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger ks-folder-delete" data-id="<?= (int) $folder['id'] ?>" data-name="<?= View::e($folder['name']) ?>" title="Supprimer"><i class="bi bi-trash"></i></button>
                            </span>
                        </div>
                        <?php $renderExplorerNodes((int) $folder['id'], true); ?>
                    </div>
                <?php else: ?>
                    <?php $bm = $node['data']; ?>
                    <div class="ks-explorer-node ks-explorer-bookmark" data-type="bookmark" data-id="<?= (int) $bm['id'] ?>">
                        <div class="ks-explorer-row">
                            <span class="ks-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                            <div class="ks-compact-dot flex-shrink-0" style="background:<?= \App\Config\BadgeStyles::gradient($bm['badge_style']) ?>"></div>
                            <a href="<?= View::e($bm['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none text-body fw-semibold flex-grow-1 text-truncate">
                                <?= View::e($bm['title'] ?: $bm['host']) ?>
                            </a>
                            <span class="text-muted small text-truncate" style="max-width:220px"><?= View::e($bm['host']) ?></span>
                            <button class="btn btn-sm btn-outline-secondary ms-2"
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
                                    data-list-id="<?= (int) $bm['list_id'] ?>"
                                    data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>"
                                    data-check-skip="<?= (int) $bm['check_skip'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="ks-quick-delete btn btn-sm btn-outline-secondary"
                                    data-delete-id="<?= $bm['id'] ?>"
                                    title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    };
    ?>

    <p class="text-muted small mb-3">Glissez-déposez dossiers et favoris pour les réorganiser.</p>

    <div class="ks-explorer-tree" id="ksExplorerTree" data-list-id="<?= (int) $listId ?>">
        <?php $renderExplorerNodes(null); ?>
    </div>
<?php endif; ?>

<?php endif; ?>

<!-- ── Modals & formulaires de gestion des dossiers (toutes vues) ─────────── -->
<?php if (!$readOnly && $listId !== null): ?>

<form method="post" action="?action=bookmark_folder_store" id="folderCreateForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="parent_id" id="folderCreateParentId" value="">
    <input type="hidden" name="name" id="folderCreateName" value="">
    <input type="hidden" name="_list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="_view" value="<?= View::e($view) ?>">
</form>

<form method="post" action="?action=bookmark_folder_rename" id="folderRenameForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="folderRenameId" value="">
    <input type="hidden" name="name" id="folderRenameName" value="">
    <input type="hidden" name="_list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="_view" value="<?= View::e($view) ?>">
</form>

<form method="post" action="?action=bookmark_folder_delete" id="folderDeleteForm" class="d-none">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <input type="hidden" name="id" id="folderDeleteId" value="">
    <input type="hidden" name="_list_id" value="<?= (int) $listId ?>">
    <input type="hidden" name="_view" value="<?= View::e($view) ?>">
</form>

<div class="modal fade ks-modal" id="folderCreateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="folderCreateModalTitle">Créer un dossier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small" id="folderCreateModalHint"></div>
                <label class="form-label">Nom du dossier</label>
                <input type="text" class="form-control" id="folderCreateModalInput" maxlength="120" placeholder="Nom du dossier">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="folderCreateModalConfirm">Créer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade ks-modal" id="folderRenameModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renommer le dossier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nouveau nom</label>
                <input type="text" class="form-control" id="folderRenameModalInput" maxlength="120" placeholder="Nouveau nom">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="folderRenameModalConfirm">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade ks-modal" id="folderDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1">
                <h6 class="modal-title fw-semibold">
                    <i class="bi bi-trash text-danger me-2"></i>Supprimer le dossier ?
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-1" id="folderDeleteModalTitle"></p>
                <p class="text-muted small mb-0">Les sous-dossiers et favoris seront remontés d'un niveau.</p>
            </div>
            <div class="modal-footer border-0 pt-1 gap-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger btn-sm" id="folderDeleteModalConfirm">
                    <i class="bi bi-trash me-1"></i>Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<?php if ($view !== 'explorer' && ($totalPages > 1 || $showAll)): ?>
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
                <input type="hidden" name="_tag" value="<?= View::e($tag) ?>">
                <input type="hidden" name="_sort" value="<?= View::e($sort) ?>">
                <input type="hidden" name="_search" value="<?= View::e($search) ?>">

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
                        <div id="bmDuplicateAlert" class="alert alert-warning py-2 px-3 mt-2 small d-none mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Ce favori existe déjà : <strong id="bmDuplicateTitle"></strong><span id="bmDuplicateList" class="text-muted"></span>
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
                        <div class="col-md-6">
                            <label class="form-label">Dossier</label>
                            <select class="form-select" name="folder_id" id="bmFolderId">
                                <option value="">— Racine de la liste —</option>
                                <?php
                                $byParentForSelect = [];
                                foreach ($allFolders ?? [] as $f) {
                                    $byParentForSelect[$f['parent_id'] !== null ? (int)$f['parent_id'] : 0][] = $f;
                                }
                                $renderFolderOption = function(int $pk, int $depth) use (&$renderFolderOption, $byParentForSelect): void {
                                    foreach ($byParentForSelect[$pk] ?? [] as $folder) {
                                        $prefix = str_repeat("\u{00A0}\u{00A0}", $depth) . ($depth > 0 ? "└\u{00A0}" : '');
                                        echo '<option value="' . (int)$folder['id'] . '"'
                                            . ' data-list-id="' . (int)$folder['list_id'] . '"'
                                            . ' data-parent-id="' . ($folder['parent_id'] !== null ? (int)$folder['parent_id'] : '') . '">'
                                            . $prefix . \App\Core\View::e($folder['name'])
                                            . '</option>';
                                        $renderFolderOption((int)$folder['id'], $depth + 1);
                                    }
                                };
                                $renderFolderOption(0, 0);
                                ?>
                            </select>
                            <div class="form-text">Le dossier doit appartenir à la liste sélectionnée.</div>
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
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnDelete">
                            <i class="bi bi-trash me-1"></i>Supprimer
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnSkipToggle"
                                title="Exclure ce favori de la vérification automatique des liens">
                            <i class="bi bi-slash-circle me-1"></i><span id="btnSkipLabel">Exclure de la vérif.</span>
                        </button>
                    </div>
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
                <input type="hidden" name="_tag" value="<?= View::e($tag) ?>">
                <input type="hidden" name="_sort" value="<?= View::e($sort) ?>">
                <input type="hidden" name="_search" value="<?= View::e($search) ?>">
            </form>

        </div>
    </div>
</div>

<!-- ── Modal confirmation suppression ────────────────────────────────────── -->
<div class="modal fade ks-modal" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1">
                <h6 class="modal-title fw-semibold">
                    <i class="bi bi-trash text-danger me-2"></i>Supprimer le favori ?
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <p class="text-muted small mb-0" id="deleteConfirmTitle"></p>
            </div>
            <div class="modal-footer border-0 pt-1 gap-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger btn-sm" id="deleteConfirmBtn">
                    <i class="bi bi-trash me-1"></i>Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="ks-data">
<?= json_encode([
    'csrf'     => $csrf,
    'view'     => $view,
    'sort'     => $sort,
    'tags'     => array_keys($allTags ?? []),
    'readOnly' => $readOnly,
    'listId'   => $listId,
], JSON_UNESCAPED_UNICODE) ?>
</script>


<?php if ($sort === 'position' || $view === 'explorer'): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<?php endif; ?>

<script src="<?= View::asset('js/bookmarks.js') ?>"></script>
<?php endif; ?>
