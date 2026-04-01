<?php

use App\Config\BadgeStyles;
use App\Core\View;

// ── Helpers ───────────────────────────────────────────────────────────────────
$pct = static fn(int $val, int $total): int => $total > 0 ? (int) round($val / $total * 100) : 0;

$statusMeta = [
    'ok'        => ['label' => 'OK',           'color' => '#10b981', 'bg' => 'rgba(16,185,129,.12)'],
    'redirect'  => ['label' => 'Redirigé',     'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.12)'],
    'error'     => ['label' => 'Inaccessible', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.12)'],
    'timeout'   => ['label' => 'Timeout',      'color' => '#f97316', 'bg' => 'rgba(249,115,22,.12)'],
    'unchecked' => ['label' => 'Non vérifié',  'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,.12)'],
];

$total        = $overview['total'];
$totalChecked = $overview['checked'];

// Mois courts en français
$monthNames = [
    '01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jui',
    '07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc',
];
$maxMonth = max(array_column($perMonth, 'cnt') ?: [1]);

$badgeStyles = BadgeStyles::all();

?>

<!-- ── En-tête ────────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="ks-admin-icon" style="background:rgba(99,102,241,.10);color:#6366f1">
        <i class="bi bi-bar-chart-fill"></i>
    </div>
    <div>
        <h1 class="fs-4 fw-bold mb-0" style="letter-spacing:-.02em">Statistiques</h1>
        <p class="text-muted small mb-0">Vue d'ensemble de l'utilisation de l'application.</p>
    </div>
    <a href="?action=admin" class="btn btn-outline-secondary btn-sm ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Administration
    </a>
</div>

<!-- ── Cartes résumé ─────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(99,102,241,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#6366f1;letter-spacing:-.03em"><?= $total ?></div>
                <div class="small text-muted mt-1">Favoris</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(16,185,129,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#10b981;letter-spacing:-.03em"><?= $overview['public'] ?></div>
                <div class="small text-muted mt-1">Publics</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(148,163,184,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#64748b;letter-spacing:-.03em"><?= $overview['private'] ?></div>
                <div class="small text-muted mt-1">Privés</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(2,136,209,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#0288D1;letter-spacing:-.03em"><?= $userCount ?></div>
                <div class="small text-muted mt-1">Utilisateurs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(245,158,11,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#f59e0b;letter-spacing:-.03em"><?= count($perList) ?></div>
                <div class="small text-muted mt-1">Listes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 h-100" style="background:rgba(20,184,166,.07);border-radius:14px">
            <div class="card-body text-center py-3 px-2">
                <div class="fs-2 fw-bold" style="color:#14b8a6;letter-spacing:-.03em"><?= count($topTags) ?></div>
                <div class="small text-muted mt-1">Tags (top)</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Activité mensuelle ─────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="border-radius:14px;background:#f8f9fa">
    <div class="card-body p-4">
        <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
            <i class="bi bi-calendar3 me-2" style="color:#6366f1"></i>Favoris ajoutés — 12 derniers mois
        </h2>
        <div class="d-flex align-items-end gap-2" style="height:120px">
            <?php foreach ($perMonth as $m): ?>
                <?php
                    $h   = $maxMonth > 0 ? max(4, (int) round($m['cnt'] / $maxMonth * 100)) : 4;
                    $key = substr($m['month'], 5, 2);
                    $lbl = ($monthNames[$key] ?? $key) . ' ' . substr($m['month'], 2, 2);
                ?>
                <div class="d-flex flex-column align-items-center flex-fill">
                    <?php if ($m['cnt'] > 0): ?>
                        <span class="small fw-semibold mb-1" style="color:#6366f1;font-size:.7rem"><?= $m['cnt'] ?></span>
                    <?php else: ?>
                        <span class="mb-1" style="font-size:.7rem">&nbsp;</span>
                    <?php endif; ?>
                    <div class="w-100 rounded-2"
                         style="height:<?= $h ?>px;background:<?= $m['cnt'] > 0 ? 'linear-gradient(180deg,#818cf8,#6366f1)' : '#e2e8f0' ?>;min-height:4px;transition:height .3s">
                    </div>
                    <span class="mt-1 text-muted" style="font-size:.65rem;white-space:nowrap"><?= $lbl ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ── Répartition par liste ─────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 h-100" style="border-radius:14px;background:#f8f9fa">
            <div class="card-body p-4">
                <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
                    <i class="bi bi-collection-fill me-2" style="color:#6366f1"></i>Par liste
                </h2>
                <?php if (empty($perList)): ?>
                    <p class="text-muted small mb-0">Aucun favori.</p>
                <?php else: ?>
                    <?php foreach ($perList as $row): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="small fw-medium text-truncate" style="max-width:65%">
                                    <?= View::e($row['name']) ?>
                                </span>
                                <span class="small text-muted">
                                    <?= $row['cnt'] ?> <span class="opacity-60">(<?= $pct((int)$row['cnt'], $total) ?>%)</span>
                                </span>
                            </div>
                            <div class="progress" style="height:6px;border-radius:99px">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $pct((int)$row['cnt'], $total) ?>%;background:linear-gradient(90deg,#818cf8,#6366f1);border-radius:99px">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Statut des liens ──────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 h-100" style="border-radius:14px;background:#f8f9fa">
            <div class="card-body p-4">
                <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
                    <i class="bi bi-link-45deg me-2" style="color:#6366f1"></i>Statut des liens
                </h2>
                <?php if ($total === 0): ?>
                    <p class="text-muted small mb-0">Aucun favori.</p>
                <?php else: ?>
                    <?php foreach ($perStatus as $row): ?>
                        <?php $meta = $statusMeta[$row['status']] ?? ['label' => $row['status'], 'color' => '#64748b', 'bg' => '#f1f5f9']; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="small fw-medium d-flex align-items-center gap-2">
                                    <span class="rounded-circle d-inline-block"
                                          style="width:8px;height:8px;background:<?= $meta['color'] ?>;flex-shrink:0"></span>
                                    <?= $meta['label'] ?>
                                </span>
                                <span class="small text-muted">
                                    <?= $row['cnt'] ?> <span class="opacity-60">(<?= $pct((int)$row['cnt'], $total) ?>%)</span>
                                </span>
                            </div>
                            <div class="progress" style="height:6px;border-radius:99px">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $pct((int)$row['cnt'], $total) ?>%;background:<?= $meta['color'] ?>;border-radius:99px">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($total > 0): ?>
                        <p class="text-muted small mb-0 mt-3 pt-2 border-top">
                            <?= $totalChecked ?> / <?= $total ?> favori<?= $total > 1 ? 's' : '' ?> vérifié<?= $total > 1 ? 's' : '' ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Top tags ──────────────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 h-100" style="border-radius:14px;background:#f8f9fa">
            <div class="card-body p-4">
                <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
                    <i class="bi bi-tags-fill me-2" style="color:#14b8a6"></i>Top tags
                </h2>
                <?php if (empty($topTags)): ?>
                    <p class="text-muted small mb-0">Aucun tag.</p>
                <?php else: ?>
                    <?php $maxTag = (int) ($topTags[0]['cnt'] ?? 1); ?>
                    <?php foreach ($topTags as $row): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="small fw-medium text-truncate" style="max-width:65%">
                                    <i class="bi bi-hash opacity-50"></i><?= View::e($row['tag']) ?>
                                </span>
                                <span class="small text-muted"><?= $row['cnt'] ?></span>
                            </div>
                            <div class="progress" style="height:6px;border-radius:99px">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $pct((int)$row['cnt'], $maxTag) ?>%;background:linear-gradient(90deg,#2dd4bf,#14b8a6);border-radius:99px">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Par utilisateur ───────────────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card border-0 h-100" style="border-radius:14px;background:#f8f9fa">
            <div class="card-body p-4">
                <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
                    <i class="bi bi-people-fill me-2" style="color:#0288D1"></i>Par utilisateur
                </h2>
                <?php if (empty($perUser)): ?>
                    <p class="text-muted small mb-0">Aucun utilisateur.</p>
                <?php else: ?>
                    <?php $maxUser = (int) max(array_column($perUser, 'cnt') ?: [1]); ?>
                    <?php foreach ($perUser as $row): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="small fw-medium text-truncate" style="max-width:65%">
                                    <i class="bi bi-person-fill me-1 opacity-50"></i><?= View::e($row['email']) ?>
                                </span>
                                <span class="small text-muted">
                                    <?= $row['cnt'] ?> <span class="opacity-60">(<?= $pct((int)$row['cnt'], $total) ?>%)</span>
                                </span>
                            </div>
                            <div class="progress" style="height:6px;border-radius:99px">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $maxUser > 0 ? $pct((int)$row['cnt'], $maxUser) : 0 ?>%;background:linear-gradient(90deg,#38bdf8,#0288D1);border-radius:99px">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Styles de badge ───────────────────────────────────────────────── -->
    <div class="col-12">
        <div class="card border-0" style="border-radius:14px;background:#f8f9fa">
            <div class="card-body p-4">
                <h2 class="fs-5 fw-semibold mb-4" style="letter-spacing:-.01em">
                    <i class="bi bi-palette-fill me-2" style="color:#f59e0b"></i>Styles de badge utilisés
                </h2>
                <?php if (empty($perBadge)): ?>
                    <p class="text-muted small mb-0">Aucun favori.</p>
                <?php else: ?>
                    <?php $maxBadge = (int) max(array_column($perBadge, 'cnt') ?: [1]); ?>
                    <div class="row g-3">
                        <?php foreach ($perBadge as $row): ?>
                            <?php
                                $style  = $badgeStyles[$row['badge_style']] ?? ['label' => $row['badge_style'], 'bg' => '#888', 'light' => '#ccc'];
                                $grad   = "linear-gradient(135deg, {$style['bg']} 0%, {$style['light']} 100%)";
                            ?>
                            <div class="col-sm-6 col-md-4 col-lg-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="rounded-2 d-inline-block flex-shrink-0"
                                          style="width:18px;height:18px;background:<?= $grad ?>"></span>
                                    <span class="small fw-medium text-truncate"><?= View::e($style['label']) ?></span>
                                    <span class="small text-muted ms-auto"><?= $row['cnt'] ?></span>
                                </div>
                                <div class="progress" style="height:5px;border-radius:99px">
                                    <div class="progress-bar" role="progressbar"
                                         style="width:<?= $pct((int)$row['cnt'], $maxBadge) ?>%;background:<?= $grad ?>;border-radius:99px">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
