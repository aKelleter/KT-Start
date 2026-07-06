<?php

use App\Core\View;

?>
<?php if (empty($lists)): ?>
    <tr><td colspan="4" class="text-muted text-center py-3">Aucune liste.</td></tr>
<?php endif; ?>
<?php foreach ($lists as $l): ?>
    <?php $isDefault = (int) $l['id'] === $defaultListId; ?>
    <tr>
        <td class="fw-semibold">
            <?= View::e($l['name']) ?>
            <?php if ($isDefault): ?>
                <i class="bi bi-star-fill text-warning ms-1" style="font-size:.8rem" title="Liste par défaut"></i>
            <?php endif; ?>
        </td>
        <td class="text-muted small"><?= (int) $l['bookmark_count'] ?> favori<?= $l['bookmark_count'] > 1 ? 's' : '' ?></td>
        <td class="text-muted small"><?= View::e(substr($l['created_at'], 0, 10)) ?></td>
        <td class="d-flex gap-1">
            <form method="post" action="?action=admin_list_set_default">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                <button type="submit"
                        class="btn btn-sm <?= $isDefault ? 'btn-warning' : 'btn-outline-secondary' ?>"
                        title="<?= $isDefault ? 'Retirer comme liste par défaut' : 'Définir comme liste par défaut' ?>">
                    <i class="bi bi-star<?= $isDefault ? '-fill' : '' ?>"></i>
                </button>
            </form>
            <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal" data-bs-target="#listModal"
                    data-mode="edit"
                    data-id="<?= $l['id'] ?>"
                    data-name="<?= View::e($l['name']) ?>">
                <i class="bi bi-pencil"></i>
            </button>
        </td>
    </tr>
<?php endforeach; ?>
