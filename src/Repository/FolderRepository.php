<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class FolderRepository
{
    /** Retourne tous les dossiers d'un utilisateur, toutes listes confondues. */
    public function findAllByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM folders WHERE user_id = :user_id ORDER BY list_id ASC, position ASC, LOWER(name) ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findAllByUserInList(int $userId, int $listId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM folders WHERE user_id = :user_id AND list_id = :list_id ORDER BY position ASC, LOWER(name) ASC'
        );
        $stmt->execute(['user_id' => $userId, 'list_id' => $listId]);
        return $stmt->fetchAll();
    }

    public function findAllByListId(int $listId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM folders WHERE list_id = :list_id ORDER BY position ASC, LOWER(name) ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = Database::connection()->prepare('SELECT * FROM folders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function existsForUserInList(int $id, int $userId, int $listId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM folders WHERE id = :id AND user_id = :user_id AND list_id = :list_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId, 'list_id' => $listId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(int $userId, int $listId, ?int $parentId, string $name): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM folders WHERE user_id = :user_id AND list_id = :list_id AND ((parent_id IS NULL AND :parent_id IS NULL) OR parent_id = :parent_id)');
        $stmt->execute(['user_id' => $userId, 'list_id' => $listId, 'parent_id' => $parentId]);
        $position = (int) $stmt->fetchColumn();

        $insert = Database::connection()->prepare(
            'INSERT INTO folders (name, user_id, list_id, parent_id, position, created_at) VALUES (:name, :user_id, :list_id, :parent_id, :position, :created_at)'
        );
        $insert->execute([
            'name' => $name,
            'user_id' => $userId,
            'list_id' => $listId,
            'parent_id' => $parentId,
            'position' => $position,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function rename(int $id, int $userId, string $name): bool
    {
        $stmt = Database::connection()->prepare('UPDATE folders SET name = :name WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['name' => $name, 'id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function setParentAndPosition(int $id, int $userId, int $listId, ?int $parentId, int $position): bool
    {
        $stmt = Database::connection()->prepare('UPDATE folders SET parent_id = :parent_id, position = :position WHERE id = :id AND user_id = :user_id AND list_id = :list_id');
        $stmt->execute([
            'parent_id' => $parentId,
            'position' => $position,
            'id' => $id,
            'user_id' => $userId,
            'list_id' => $listId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function wouldCreateCycle(int $folderId, ?int $parentId, int $userId, int $listId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === $folderId) {
            return true;
        }

        $current = $this->findById($parentId);
        while ($current) {
            if ((int) $current['user_id'] !== $userId || (int) $current['list_id'] !== $listId) {
                return true;
            }

            $nextParent = $current['parent_id'] !== null ? (int) $current['parent_id'] : null;
            if ($nextParent === null) {
                return false;
            }
            if ($nextParent === $folderId) {
                return true;
            }

            $current = $this->findById($nextParent);
        }

        return false;
    }

    /** Vérifie un cycle sans contrainte user_id (usage admin) */
    public function wouldCreateCycleAdmin(int $folderId, int $parentId): bool
    {
        if ($parentId === $folderId) {
            return true;
        }
        $current = $this->findById($parentId);
        while ($current) {
            $nextParent = $current['parent_id'] !== null ? (int) $current['parent_id'] : null;
            if ($nextParent === null) {
                return false;
            }
            if ($nextParent === $folderId) {
                return true;
            }
            $current = $this->findById($nextParent);
        }
        return false;
    }

    public function deleteAndLiftChildren(int $id, int $userId): bool
    {
        $folder = $this->findById($id);
        if (!$folder || (int) $folder['user_id'] !== $userId) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;

            // Move subfolders one level up instead of hard-deleting a subtree.
            $upFolders = $pdo->prepare(
                'UPDATE folders SET parent_id = :parent_id WHERE parent_id = :id AND user_id = :user_id AND list_id = :list_id'
            );
            $upFolders->execute([
                'parent_id' => $parentId,
                'id' => $id,
                'user_id' => $userId,
                'list_id' => (int) $folder['list_id'],
            ]);

            // Keep bookmarks by moving them to the parent folder (or root if null).
            $upBookmarks = $pdo->prepare(
                'UPDATE bookmarks SET folder_id = :parent_id WHERE folder_id = :id AND user_id = :user_id AND list_id = :list_id'
            );
            $upBookmarks->execute([
                'parent_id' => $parentId,
                'id' => $id,
                'user_id' => $userId,
                'list_id' => (int) $folder['list_id'],
            ]);

            $del = $pdo->prepare('DELETE FROM folders WHERE id = :id AND user_id = :user_id');
            $del->execute(['id' => $id, 'user_id' => $userId]);

            $pdo->commit();
            return $del->rowCount() > 0;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }

    public function findAllByListIdWithUser(int $listId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.*, u.email as user_email FROM folders f JOIN users u ON f.user_id = u.id WHERE f.list_id = :list_id ORDER BY f.position ASC, LOWER(f.name) ASC'
        );
        $stmt->execute(['list_id' => $listId]);
        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM folders')->fetchColumn();
    }

    public function renameAdmin(int $id, string $name): bool
    {
        $stmt = Database::connection()->prepare('UPDATE folders SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function setParentAndPositionAdmin(int $id, int $listId, ?int $parentId, int $position): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE folders SET parent_id = :parent_id, position = :position WHERE id = :id AND list_id = :list_id'
        );
        $stmt->execute(['parent_id' => $parentId, 'position' => $position, 'id' => $id, 'list_id' => $listId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteAndLiftChildrenAdmin(int $id): bool
    {
        $folder = $this->findById($id);
        if (!$folder) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;

            $pdo->prepare('UPDATE folders SET parent_id = :parent_id WHERE parent_id = :id AND list_id = :list_id')
                ->execute(['parent_id' => $parentId, 'id' => $id, 'list_id' => (int) $folder['list_id']]);

            $pdo->prepare('UPDATE bookmarks SET folder_id = :parent_id WHERE folder_id = :id AND list_id = :list_id')
                ->execute(['parent_id' => $parentId, 'id' => $id, 'list_id' => (int) $folder['list_id']]);

            $del = $pdo->prepare('DELETE FROM folders WHERE id = :id');
            $del->execute(['id' => $id]);

            $pdo->commit();
            return $del->rowCount() > 0;
        } catch (\Throwable) {
            $pdo->rollBack();
            return false;
        }
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function groupByParent(array $folders): array
    {
        $grouped = [];
        foreach ($folders as $folder) {
            $key = $folder['parent_id'] === null ? 0 : (int) $folder['parent_id'];
            $grouped[$key][] = $folder;
        }
        return $grouped;
    }
}
