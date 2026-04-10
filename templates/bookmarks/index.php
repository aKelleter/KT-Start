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
                <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok'): ?>
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
                    data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>">
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
                <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok'): ?>
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
                        data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>">
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

<div class="table-responsive">
    <table class="table table-hover table-sm align-middle ks-table">
        <thead class="table-light">
            <tr>
                <th style="width:36px"></th>
                <th>Titre / Domaine</th>
                <th>Liste</th>
                <th>Tags</th>
                <th>Visibilité</th>
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
        <?php if (!empty($bm['last_check_status']) && $bm['last_check_status'] !== 'ok'): ?>
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
                data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>">
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
                echo '<div class="ks-compact-list mb-2">';
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
        <div class="ks-compact-list mb-2">
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
                                    data-folder-id="<?= $bm['folder_id'] !== null ? (int) $bm['folder_id'] : '' ?>">
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

<!-- ── JS modal ───────────────────────────────────────────────────────────── -->
<script>
(function () {
    const modal     = document.getElementById('bookmarkModal');
    const form      = document.getElementById('bookmarkForm');
    const deleteForm = document.getElementById('deleteForm');
    const folderSelect = document.getElementById('bmFolderId');

    function syncFolderOptionsByList(listId) {
        if (!folderSelect) return;
        const target = String(listId || '');
        [...folderSelect.options].forEach((opt, idx) => {
            if (idx === 0) {
                opt.hidden = false;
                opt.disabled = false;
                return;
            }
            const belongs = opt.dataset.listId === target;
            opt.hidden = !belongs;
            opt.disabled = !belongs;
        });

        const selected = folderSelect.options[folderSelect.selectedIndex];
        if (selected && selected.disabled) {
            folderSelect.value = '';
        }
    }

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

        document.getElementById('bmDuplicateAlert').classList.add('d-none');

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
            syncFolderOptionsByList(btn.dataset.listId);
            folderSelect.value = btn.dataset.folderId || '';
            document.getElementById('bmVisibility').value  = btn.dataset.visibility;
            document.getElementById('deleteId').value      = btn.dataset.id;
            selectBadgeStyle(btn.dataset.badgeStyle);
        } else {
            form.reset();
            document.getElementById('bmId').value = '';
            selectList('', '— Aucune —');
            syncFolderOptionsByList('');
            folderSelect.value = '';
            selectBadgeStyle('deepBlue');
        }
    });

    // Confirmation suppression
    let deleteFromEditModal = false;

    function openDeleteConfirm(id, title, fromEdit) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteConfirmTitle').textContent = title || '';
        deleteFromEditModal = !!fromEdit;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteConfirmModal')).show();
    }

    document.getElementById('deleteConfirmBtn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'))?.hide();
        if (deleteFromEditModal) {
            bootstrap.Modal.getInstance(document.getElementById('bookmarkModal'))?.hide();
        }
        deleteForm.submit();
    });

    // Delete button (depuis le modal d'édition)
    document.getElementById('btnDelete').addEventListener('click', function () {
        const title = document.getElementById('bmTitle').value
                   || document.getElementById('bmUrl').value;
        openDeleteConfirm(document.getElementById('deleteId').value, title, true);
    });

    // Quick delete buttons (inline dans les vues)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.ks-quick-delete');
        if (!btn) return;
        const row = btn.closest('[data-id]');
        const title = row?.querySelector('a[href]')?.textContent.trim()
                   || btn.dataset.deleteId;
        openDeleteConfirm(btn.dataset.deleteId, title, false);
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

    // Duplicate check
    document.getElementById('bmUrl').addEventListener('blur', async function () {
        const url   = this.value.trim();
        const alert = document.getElementById('bmDuplicateAlert');
        alert.classList.add('d-none');
        if (!url) return;

        const excludeId = document.getElementById('bmId').value;
        const params = '?action=bookmark_check_duplicate&url=' + encodeURIComponent(url)
                     + (excludeId ? '&exclude_id=' + encodeURIComponent(excludeId) : '');
        try {
            const data = await fetch(params).then(r => r.json());
            if (data.duplicate) {
                document.getElementById('bmDuplicateTitle').textContent = data.title;
                document.getElementById('bmDuplicateList').textContent  = data.list_name ? ' — ' + data.list_name : '';
                alert.classList.remove('d-none');
            }
        } catch (e) { /* silencieux */ }
    });

    // Liste picker
    function selectList(value, label) {
        document.getElementById('bmListId').value  = value;
        document.getElementById('bmListLabel').textContent = label || '— Aucune —';
        syncFolderOptionsByList(value);
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

    // Autocomplete tags multi-valeur (séparés par virgule)
    (function () {
        const input = document.getElementById('bmTags');
        if (!input) return;
        const tags = <?= json_encode(array_keys($allTags ?? []), JSON_UNESCAPED_UNICODE) ?>;
        if (!tags.length) return;

        const dl = document.createElement('datalist');
        dl.id = '_dl_bmTags';
        input.setAttribute('list', dl.id);
        document.body.appendChild(dl);

        function refresh() {
            const val    = input.value;
            const comma  = val.lastIndexOf(',');
            const prefix = comma >= 0 ? val.substring(0, comma + 1) + ' ' : '';
            const done   = val.split(',').map(t => t.trim()).filter(Boolean);
            dl.innerHTML = tags
                .filter(t => !done.includes(t))
                .map(t => `<option value="${prefix}${t}"></option>`)
                .join('');
        }
        input.addEventListener('input', refresh);
        input.addEventListener('focus', refresh);
    })();
})();
</script>

<?php if ($sort === 'position' || $view === 'explorer' || (!$readOnly && $listId !== null)): ?>
<?php if ($sort === 'position' || $view === 'explorer'): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<?php endif; ?>
<script>
(function () {
    const csrf = <?= json_encode($csrf) ?>;
    const view = <?= json_encode($view) ?>;

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
        if (!el || typeof Sortable === 'undefined') return;
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

    // ── Tri drag&drop (vues badges/tableau/liste) ────────────────────────────
    if (view !== 'explorer') {
        // Vue badges : cross-folder si data-folder-id présent, sinon tri simple
        const folderGrids = document.querySelectorAll('.ks-badges-grid[data-folder-id]');
        if (folderGrids.length > 0 && typeof Sortable !== 'undefined') {
            const listId = parseInt(
                (document.getElementById('ksFolderList') || folderGrids[0]).dataset.listId || '0', 10
            );

            // Réorganisation des dossiers eux-mêmes
            const folderList = document.getElementById('ksFolderList');
            if (folderList) {
                new Sortable(folderList, {
                    handle: '.ks-drag-handle',
                    animation: 150,
                    ghostClass: 'ks-sortable-ghost',
                    chosenClass: 'ks-sortable-chosen',
                    async onEnd() {
                        const items = [...folderList.querySelectorAll(':scope > .ks-folder-group')]
                            .map((el, pos) => ({ type: 'folder', id: parseInt(el.dataset.folderId, 10), pos }))
                            .filter(i => i.id);
                        try {
                            await fetch('?action=bookmark_explorer_reorder', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    _csrf: csrf,
                                    list_id: listId,
                                    parent_id: null,
                                    items: items.map(i => ({ type: i.type, id: i.id })),
                                }),
                            });
                        } catch (e) { console.error('Erreur ordre dossiers:', e); }
                    },
                });
            }

            let pendingBadgeSave = new Set();
            let badgeSaveTimer = null;

            function scheduleBadgeSave(container) {
                pendingBadgeSave.add(container);
                clearTimeout(badgeSaveTimer);
                badgeSaveTimer = setTimeout(() => {
                    const toSave = [...pendingBadgeSave];
                    pendingBadgeSave.clear();
                    toSave.forEach(saveBadgesContainer);
                }, 300);
            }

            async function saveBadgesContainer(container) {
                const folderId = container.dataset.folderId;
                const parentId = (folderId === '' || folderId === undefined) ? null : parseInt(folderId, 10);
                const items = [...container.querySelectorAll(':scope > .ks-badge')]
                    .map(el => ({ type: 'bookmark', id: parseInt(el.dataset.id, 10) }))
                    .filter(i => i.id);
                try {
                    await fetch('?action=bookmark_explorer_reorder', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ _csrf: csrf, list_id: listId, parent_id: parentId, items }),
                    });
                } catch (e) { console.error('Erreur sauvegarde dossier badge:', e); }
            }

            function updateFolderCount(grid) {
                const folderId = grid.dataset.folderId;
                if (folderId === undefined) return;
                // Compte les badges dans TOUTES les grids de ce dossier (visible + collapse)
                const allGrids = folderId === ''
                    ? document.querySelectorAll('.ks-badges-grid[data-folder-id=""]')
                    : document.querySelectorAll(`.ks-badges-grid[data-folder-id="${folderId}"]`);
                let total = 0;
                allGrids.forEach(g => { total += g.querySelectorAll(':scope > .ks-badge').length; });
                if (folderId !== '') {
                    const badge = document.querySelector(`[data-folder-count="${folderId}"]`);
                    if (badge) badge.textContent = total;
                }
            }

            // ── Hover-to-open : ouvre un dossier replié après 600ms de survol ──
            let hoverTimer = null;
            let hoverOpenedTriggers = new Set();

            function cancelHoverOpen() {
                clearTimeout(hoverTimer);
                hoverTimer = null;
            }

            function closeHoverOpenedFolders() {
                hoverOpenedTriggers.forEach(trigger => {
                    const targetId = (trigger.dataset.bsTarget || '').replace('#', '');
                    const collapseEl = targetId ? document.getElementById(targetId) : null;
                    if (collapseEl) bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
                    collapseEl?.querySelectorAll('.ks-drop-target-empty').forEach(g => g.classList.remove('ks-drop-target-empty'));
                });
                hoverOpenedTriggers.clear();
            }

            document.querySelectorAll('.ks-folder-group').forEach(group => {
                group.addEventListener('dragover', e => {
                    e.preventDefault();
                    const trigger = group.querySelector('[data-bs-toggle="collapse"]');
                    if (!trigger || !trigger.classList.contains('collapsed')) return;
                    if (hoverTimer) return;
                    hoverTimer = setTimeout(() => {
                        hoverTimer = null;
                        const targetId = (trigger.dataset.bsTarget || '').replace('#', '');
                        const collapseEl = targetId ? document.getElementById(targetId) : null;
                        if (!collapseEl) return;
                        hoverOpenedTriggers.add(trigger);
                        bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
                        collapseEl.querySelectorAll('.ks-badges-grid').forEach(g => {
                            if (!g.querySelector('.ks-badge:not(.ks-folder-badge)')) g.classList.add('ks-drop-target-empty');
                        });
                    }, 350);
                });
                group.addEventListener('dragleave', cancelHoverOpen);
            });

            folderGrids.forEach(grid => {
                new Sortable(grid, {
                    group: 'ks-badges',
                    handle: '.ks-drag-handle',
                    animation: 150,
                    ghostClass: 'ks-sortable-ghost',
                    chosenClass: 'ks-sortable-chosen',
                    onEnd(evt) {
                        cancelHoverOpen();
                        // Ferme les dossiers ouverts par survol, SAUF celui où le favori a été déposé
                        const droppedIntoCollapse = evt.to.closest('.collapse');
                        hoverOpenedTriggers.forEach(trigger => {
                            const targetId = (trigger.dataset.bsTarget || '').replace('#', '');
                            const collapseEl = targetId ? document.getElementById(targetId) : null;
                            if (collapseEl && collapseEl === droppedIntoCollapse) return; // garder ouvert
                            if (collapseEl) {
                                bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
                                collapseEl.querySelectorAll('.ks-drop-target-empty').forEach(g => g.classList.remove('ks-drop-target-empty'));
                            }
                        });
                        hoverOpenedTriggers.clear();
                        scheduleBadgeSave(evt.to);
                        if (evt.from !== evt.to) {
                            scheduleBadgeSave(evt.from);
                            updateFolderCount(evt.to);
                            updateFolderCount(evt.from);
                        }
                        // Maj visuel zone vide
                        [evt.to, evt.from].forEach(g => {
                            const empty = g.querySelectorAll(':scope > .ks-badge').length === 0;
                            g.classList.toggle('ks-drop-target-empty', empty);
                        });
                    },
                });
            });
        } else {
            document.querySelectorAll('.ks-badges-grid').forEach(el => makeSortable(el, '.ks-badge'));
        }

        document.querySelectorAll('.ks-compact-list').forEach(el => makeSortable(el, '.ks-compact-item'));
        makeSortable(document.querySelector('table tbody'), 'tr');
    }

    // ── Drag&drop explorateur ─────────────────────────────────────────────────
    const tree = document.getElementById('ksExplorerTree');
    if (tree) {
        async function saveExplorerContainer(container) {
            const listId = parseInt(tree.dataset.listId || '0', 10);
            if (!listId) return;
            const parentRaw = container.dataset.parentId;
            const parentId = parentRaw === '' ? null : parseInt(parentRaw, 10);
            const items = [...container.children]
                .filter(node => node.classList.contains('ks-explorer-node'))
                .map(node => ({ type: node.dataset.type, id: parseInt(node.dataset.id, 10) }))
                .filter(i => i.type && i.id);
            await fetch('?action=bookmark_explorer_reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: csrf, list_id: listId, parent_id: parentId, items }),
            });
        }
        document.querySelectorAll('.ks-explorer-dropzone').forEach(container => {
            new Sortable(container, {
                group: 'ks-explorer',
                handle: '.ks-drag-handle',
                animation: 150,
                ghostClass: 'ks-sortable-ghost',
                chosenClass: 'ks-sortable-chosen',
                onEnd(evt) {
                    const promises = [saveExplorerContainer(evt.to)];
                    if (evt.from && evt.from !== evt.to) promises.push(saveExplorerContainer(evt.from));
                    Promise.all(promises).catch(console.error);
                },
            });
        });
    }

    // ── Gestion des dossiers (toutes les vues) ────────────────────────────────
    const folderCreateModalEl = document.getElementById('folderCreateModal');
    if (!folderCreateModalEl) return;

    const folderRenameModalEl = document.getElementById('folderRenameModal');
    const folderDeleteModalEl = document.getElementById('folderDeleteModal');
    const folderCreateInput  = document.getElementById('folderCreateModalInput');
    const folderRenameInput  = document.getElementById('folderRenameModalInput');

    let pendingCreateParentId = '';
    let pendingRenameId = '';
    let pendingDeleteId = '';

    folderCreateModalEl.addEventListener('shown.bs.modal', () => folderCreateInput?.focus());
    folderRenameModalEl.addEventListener('shown.bs.modal', () => folderRenameInput?.focus());

    folderCreateInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('folderCreateModalConfirm').click();
    });
    folderRenameInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') document.getElementById('folderRenameModalConfirm').click();
    });

    document.getElementById('folderCreateModalConfirm').addEventListener('click', () => {
        const name = folderCreateInput.value.trim();
        if (!name) { folderCreateInput.focus(); return; }
        document.getElementById('folderCreateParentId').value = pendingCreateParentId;
        document.getElementById('folderCreateName').value = name;
        bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).hide();
        document.getElementById('folderCreateForm').submit();
    });

    document.getElementById('folderRenameModalConfirm').addEventListener('click', () => {
        const name = folderRenameInput.value.trim();
        if (!name) { folderRenameInput.focus(); return; }
        document.getElementById('folderRenameId').value = pendingRenameId;
        document.getElementById('folderRenameName').value = name;
        bootstrap.Modal.getOrCreateInstance(folderRenameModalEl).hide();
        document.getElementById('folderRenameForm').submit();
    });

    document.getElementById('folderDeleteModalConfirm').addEventListener('click', () => {
        document.getElementById('folderDeleteId').value = pendingDeleteId;
        bootstrap.Modal.getOrCreateInstance(folderDeleteModalEl).hide();
        document.getElementById('folderDeleteForm').submit();
    });

    function toggleExplorerFolder(node) {
        const childZone = node?.querySelector(':scope > .ks-explorer-dropzone');
        if (!childZone) return;
        const toggle = node.querySelector(':scope > .ks-explorer-row .ks-folder-toggle');
        const collapsed = childZone.classList.toggle('d-none');
        toggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle?.querySelector('i')?.classList.toggle('bi-caret-right-fill', collapsed);
        toggle?.querySelector('i')?.classList.toggle('bi-caret-down-fill', !collapsed);
    }

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('.ks-folder-toggle');
        if (toggle) {
            toggleExplorerFolder(toggle.closest('.ks-explorer-folder'));
            return;
        }

        // Clic sur la ligne du dossier (hors boutons et poignée) → toggle
        if (!e.target.closest('button') && !e.target.closest('.ks-drag-handle')) {
            const row = e.target.closest('.ks-explorer-folder > .ks-explorer-row');
            if (row) {
                toggleExplorerFolder(row.closest('.ks-explorer-folder'));
                return;
            }
        }

        const createChild = e.target.closest('.ks-folder-create-child');
        if (createChild) {
            pendingCreateParentId = createChild.dataset.id || '';
            document.getElementById('folderCreateModalTitle').textContent = 'Créer un sous-dossier';
            document.getElementById('folderCreateModalHint').textContent = 'Dans ' + (createChild.dataset.name || 'ce dossier');
            folderCreateInput.value = '';
            bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).show();
            return;
        }

        const rename = e.target.closest('.ks-folder-rename');
        if (rename) {
            pendingRenameId = rename.dataset.id || '';
            folderRenameInput.value = rename.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(folderRenameModalEl).show();
            return;
        }

        const del = e.target.closest('.ks-folder-delete');
        if (del) {
            pendingDeleteId = del.dataset.id || '';
            document.getElementById('folderDeleteModalTitle').textContent = del.dataset.name || '';
            bootstrap.Modal.getOrCreateInstance(folderDeleteModalEl).show();
            return;
        }

        const createRoot = e.target.closest('#btnFolderCreateRoot');
        if (createRoot) {
            pendingCreateParentId = '';
            document.getElementById('folderCreateModalTitle').textContent = 'Créer un dossier';
            document.getElementById('folderCreateModalHint').textContent = 'À la racine de la liste';
            folderCreateInput.value = '';
            bootstrap.Modal.getOrCreateInstance(folderCreateModalEl).show();
        }
    });
})();
</script>
<?php endif; ?>

<?php endif; ?>

<!-- ── JS toggle liste dropdown/boutons ──────────────────────────────────── -->
<script>
(function () {
    const KEY       = 'ks-list-nav';
    const toggleBtn = document.getElementById('btnListNavToggle');
    if (!toggleBtn) return;

    let mode = document.documentElement.dataset.listNav || '<?= $listNavStyle ?>';

    function applyIcon(m) {
        if (m === 'buttons') {
            toggleBtn.innerHTML = '<i class="bi bi-menu-button-wide-fill"></i>';
            toggleBtn.title = 'Passer en liste déroulante';
        } else {
            toggleBtn.innerHTML = '<i class="bi bi-collection"></i>';
            toggleBtn.title = 'Passer en boutons';
        }
    }

    applyIcon(mode);

    toggleBtn.addEventListener('click', function () {
        const next    = mode === 'buttons' ? 'dropdown' : 'buttons';
        const leaving = document.getElementById(mode === 'buttons' ? 'ks-list-nav-buttons' : 'ks-list-filter-dropdown');

        const DURATION = 400;
        const EASING   = 'opacity ' + DURATION + 'ms ease, transform ' + DURATION + 'ms ease';

        function doSwitch() {
            mode = next;
            localStorage.setItem(KEY, mode);
            document.documentElement.dataset.listNav = mode;
            applyIcon(mode);

            const entering = document.getElementById(mode === 'buttons' ? 'ks-list-nav-buttons' : 'ks-list-filter-dropdown');
            if (entering) {
                entering.style.opacity   = '0';
                entering.style.transform = 'translateY(-5px)';
                entering.getBoundingClientRect(); // force reflow
                entering.style.transition = EASING;
                entering.style.opacity    = '1';
                entering.style.transform  = 'translateY(0)';
                setTimeout(() => {
                    entering.style.transition = '';
                    entering.style.opacity    = '';
                    entering.style.transform  = '';
                }, DURATION);
            }
        }

        if (leaving) {
            leaving.style.transition = 'opacity .15s ease, transform .15s ease';
            leaving.style.opacity    = '0';
            leaving.style.transform  = 'translateY(-5px)';
            setTimeout(() => {
                leaving.style.transition = '';
                leaving.style.opacity    = '';
                leaving.style.transform  = '';
                doSwitch();
            }, 150);
        } else {
            doSwitch();
        }
    });
})();
</script>

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

<?php if ($view === 'badges'): ?>
<script>
(function () {
    const SIZES  = [80, 105, 130, 160, 195, 230];
    const LABELS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    const KEY    = 'ks-badge-size';

    const btnSmaller = document.getElementById('btnBadgeSmaller');
    const btnLarger  = document.getElementById('btnBadgeLarger');
    const label      = document.getElementById('badgeSizeLabel');

    const saved = parseInt(localStorage.getItem(KEY) || '160', 10);
    let idx = SIZES.indexOf(saved);
    if (idx === -1) idx = SIZES.findIndex(s => s >= saved) ?? 2;
    idx = Math.max(0, Math.min(SIZES.length - 1, idx));

    function apply() {
        const size = SIZES[idx];
        document.documentElement.style.setProperty('--ks-badge-width', size + 'px');
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

<?php if (!$readOnly): ?>
<!-- ── Raccourci clavier N → modal d'ajout ───────────────────────────────── -->
<script>
(function () {
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'n' && e.key !== 'N') return;
        const tag = (document.activeElement?.tagName ?? '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select'
            || document.activeElement?.isContentEditable) return;
        const btn = document.querySelector('[data-bs-target="#bookmarkModal"][data-mode="add"]');
        if (btn) btn.click();
    });
})();
</script>
<?php endif; ?>
