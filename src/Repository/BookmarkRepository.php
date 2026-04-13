<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class BookmarkRepository
{
    // ── Helpers WHERE ─────────────────────────────────────────────────────────

    /** @return array{array<string>, array<string, mixed>} */
    private function publicWhere(?int $listId, ?string $tag, ?string $search): array
    {
        $where  = ["b.visibility = 'public'"];
        $params = [];

        if ($listId !== null) {
            $where[]           = 'b.list_id = :list_id';
            $params['list_id'] = $listId;
        }
        if ($tag !== null && $tag !== '') {
            $where[]       = "(',' || b.tags || ',' LIKE :tag)";
            $params['tag'] = '%,' . $tag . ',%';
        }
        if ($search !== null && $search !== '') {
            $where[]     = '(b.title LIKE :q OR b.host LIKE :q OR b.url LIKE :q OR b.description LIKE :q OR b.tags LIKE :q OR b.badge_text LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return [$where, $params];
    }

    /** @return array{array<string>, array<string, mixed>} */
    private function filteredWhere(int $userId, ?int $listId, ?string $tag, ?string $search): array
    {
        $where  = ['b.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($listId !== null) {
            $where[]           = 'b.list_id = :list_id';
            $params['list_id'] = $listId;
        }
        if ($tag !== null && $tag !== '') {
            $where[]       = "(',' || b.tags || ',' LIKE :tag)";
            $params['tag'] = '%,' . $tag . ',%';
        }
        if ($search !== null && $search !== '') {
            $where[]     = '(b.title LIKE :q OR b.host LIKE :q OR b.url LIKE :q OR b.description LIKE :q OR b.tags LIKE :q OR b.badge_text LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return [$where, $params];
    }

    private function orderBy(string $sort): string
    {
        return match ($sort) {
            'title'           => 'LOWER(b.title) ASC',
            'title_desc'      => 'LOWER(b.title) DESC',
            'host'            => 'LOWER(b.host) ASC',
            'host_desc'       => 'LOWER(b.host) DESC',
            'badge_text'      => 'LOWER(b.badge_text) ASC',
            'date_asc'        => 'b.created_at ASC',
            'date_desc'       => 'b.created_at DESC',
            'list_asc'        => 'LOWER(l.name) ASC, b.position ASC',
            'list_desc'       => 'LOWER(l.name) DESC, b.position ASC',
            'visibility_asc'  => 'b.visibility ASC, b.position ASC',
            'visibility_desc' => 'b.visibility DESC, b.position ASC',
            default           => 'b.position ASC, b.created_at DESC',
        };
    }

    // ── Lecture publique ──────────────────────────────────────────────────────

    public function findPublic(
        ?int $listId, ?string $tag, string $sort, ?string $search = null,
        int $limit = 0, int $offset = 0
    ): array {
        [$where, $params] = $this->publicWhere($listId, $tag, $search);

        $limitSql = $limit > 0 ? "LIMIT $limit OFFSET $offset" : '';
        $sql = "
            SELECT b.*, l.name AS list_name, f.name AS folder_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            LEFT JOIN folders f ON f.id = b.folder_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$this->orderBy($sort)}
            $limitSql
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countPublic(?int $listId, ?string $tag, ?string $search = null): int
    {
        [$where, $params] = $this->publicWhere($listId, $tag, $search);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM bookmarks b WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findPublicByList(int $listId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM bookmarks WHERE visibility = \'public\' AND list_id = :list_id ORDER BY position ASC, created_at DESC'
        );
        $stmt->execute(['list_id' => $listId]);
        return $stmt->fetchAll();
    }

    // ── Lecture filtrée (connecté) ────────────────────────────────────────────

    public function findFiltered(
        int $userId, ?int $listId, ?string $tag, string $sort, ?string $search = null,
        int $limit = 0, int $offset = 0
    ): array {
        [$where, $params] = $this->filteredWhere($userId, $listId, $tag, $search);

        $limitSql = $limit > 0 ? "LIMIT $limit OFFSET $offset" : '';
        $sql = "
            SELECT b.*, l.name AS list_name, f.name AS folder_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            LEFT JOIN folders f ON f.id = b.folder_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$this->orderBy($sort)}
            $limitSql
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(int $userId, ?int $listId, ?string $tag, ?string $search = null): int
    {
        [$where, $params] = $this->filteredWhere($userId, $listId, $tag, $search);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM bookmarks b WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function findById(int $id): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM bookmarks WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare("
            INSERT INTO bookmarks
                (url, host, title, description, badge_style, badge_text, tags,
                 visibility, list_id, folder_id, user_id, position, created_at)
            VALUES
                (:url, :host, :title, :description, :badge_style, :badge_text, :tags,
                 :visibility, :list_id, :folder_id, :user_id, :position, :created_at)
        ");
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets       = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;

        $stmt = Database::connection()->prepare(
            "UPDATE bookmarks SET $sets WHERE id = :id"
        );
        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM bookmarks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function reorder(int $userId, array $ids): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET position = :pos WHERE id = :id AND user_id = :uid'
        );
        foreach ($ids as $pos => $id) {
            $stmt->execute(['pos' => $pos, 'id' => (int) $id, 'uid' => $userId]);
        }
    }

    // ── Tags ──────────────────────────────────────────────────────────────────

    /** @return array<string, int>  tag => count, trié par fréquence décroissante (user courant) */
    public function getAllTags(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT tags FROM bookmarks WHERE user_id = :user_id AND tags IS NOT NULL AND tags != ''"
        );
        $stmt->execute(['user_id' => $userId]);

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (explode(',', $row['tags']) as $t) {
                $t = trim($t);
                if ($t !== '') {
                    $tags[$t] = ($tags[$t] ?? 0) + 1;
                }
            }
        }

        arsort($tags); // fréquence décroissante
        return $tags;
    }

    /** @return array<string, int>  tag => count, tous utilisateurs, trié par fréquence décroissante */
    public function getAllTagsAdmin(): array
    {
        $stmt = Database::connection()->query(
            "SELECT tags FROM bookmarks WHERE tags IS NOT NULL AND tags != ''"
        );

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (explode(',', $row['tags']) as $t) {
                $t = trim($t);
                if ($t !== '') {
                    $tags[$t] = ($tags[$t] ?? 0) + 1;
                }
            }
        }

        arsort($tags);
        return $tags;
    }

    /** Renomme un tag dans tous les favoris. Retourne le nombre de favoris modifiés. */
    public function renameTag(string $old, string $new): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, tags FROM bookmarks WHERE (',' || tags || ',' LIKE :tag)"
        );
        $stmt->execute(['tag' => '%,' . $old . ',%']);
        $rows = $stmt->fetchAll();

        $update = Database::connection()->prepare(
            'UPDATE bookmarks SET tags = :tags WHERE id = :id'
        );

        $count = 0;
        foreach ($rows as $row) {
            $tags = array_map('trim', explode(',', $row['tags']));
            $tags = array_map(fn($t) => $t === $old ? $new : $t, $tags);
            $tags = array_values(array_unique(array_filter($tags, fn($t) => $t !== '')));
            $update->execute(['tags' => implode(',', $tags), 'id' => $row['id']]);
            $count++;
        }

        return $count;
    }

    /**
     * Supprime tous les tags utilisés par un seul favori.
     * Retourne le nombre de tags supprimés.
     */
    public function deleteTagsUsedOnce(): int
    {
        $stmt = Database::connection()->query(
            "SELECT id, tags FROM bookmarks WHERE tags IS NOT NULL AND tags != ''"
        );
        $rows = $stmt->fetchAll();

        // Compter la fréquence de chaque tag
        $freq = [];
        foreach ($rows as $row) {
            foreach (array_map('trim', explode(',', $row['tags'])) as $t) {
                if ($t !== '') {
                    $freq[$t] = ($freq[$t] ?? 0) + 1;
                }
            }
        }

        $singles = array_keys(array_filter($freq, fn($c) => $c === 1));
        if (empty($singles)) {
            return 0;
        }

        $singlesSet = array_flip($singles);
        $update     = Database::connection()->prepare(
            'UPDATE bookmarks SET tags = :tags WHERE id = :id'
        );

        foreach ($rows as $row) {
            $tags    = array_map('trim', explode(',', $row['tags']));
            $cleaned = array_values(array_filter($tags, fn($t) => $t !== '' && !isset($singlesSet[$t])));
            if (count($cleaned) !== count(array_filter($tags, fn($t) => $t !== ''))) {
                $update->execute(['tags' => implode(',', $cleaned), 'id' => $row['id']]);
            }
        }

        return count($singles);
    }

    /** Supprime un tag de tous les favoris. Retourne le nombre de favoris modifiés. */
    public function deleteTag(string $tag): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, tags FROM bookmarks WHERE (',' || tags || ',' LIKE :tag)"
        );
        $stmt->execute(['tag' => '%,' . $tag . ',%']);
        $rows = $stmt->fetchAll();

        $update = Database::connection()->prepare(
            'UPDATE bookmarks SET tags = :tags WHERE id = :id'
        );

        $count = 0;
        foreach ($rows as $row) {
            $tags = array_map('trim', explode(',', $row['tags']));
            $tags = array_values(array_filter($tags, fn($t) => $t !== '' && $t !== $tag));
            $update->execute(['tags' => implode(',', $tags), 'id' => $row['id']]);
            $count++;
        }

        return $count;
    }

    // ── Vérification des liens ────────────────────────────────────────────────

    /** Retourne tous les favoris d'un utilisateur avec leur URL (pour vérification). */
    public function findAllByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, url, title, host, last_check_status, last_check_at, last_http_code, check_skip FROM bookmarks WHERE user_id = :user_id ORDER BY position ASC, created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByUserAndListWithFolder(int $userId, int $listId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM bookmarks WHERE user_id = :user_id AND list_id = :list_id ORDER BY position ASC, created_at DESC'
        );
        $stmt->execute(['user_id' => $userId, 'list_id' => $listId]);
        return $stmt->fetchAll();
    }

    public function setFolderAndPosition(int $id, int $userId, int $listId, ?int $folderId, int $position): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET folder_id = :folder_id, position = :position WHERE id = :id AND user_id = :user_id AND list_id = :list_id'
        );
        $stmt->execute([
            'folder_id' => $folderId,
            'position' => $position,
            'id' => $id,
            'user_id' => $userId,
            'list_id' => $listId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Met à jour le statut de vérification d'un favori. */
    public function updateCheckStatus(int $id, string $status, string $checkedAt, int $httpCode = 0): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET last_check_status = :status, last_check_at = :checked_at, last_http_code = :http_code WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'checked_at' => $checkedAt, 'http_code' => $httpCode ?: null, 'id' => $id]);
    }

    public function updateCheckSkip(int $id, bool $skip): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET check_skip = :skip WHERE id = :id'
        );
        $stmt->execute(['skip' => $skip ? 1 : 0, 'id' => $id]);
    }

    /** Remet à zéro le statut de vérification de tous les favoris d'un utilisateur. */
    public function resetCheckStatus(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET last_check_status = NULL, last_check_at = NULL WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    /** Compte les favoris dont le statut est 'error' ou 'timeout' (tous utilisateurs).
     *  Retourne 0 si la colonne last_check_status n'existe pas encore (migration non lancée). */
    public function countDeadLinksAll(): int
    {
        try {
            $stmt = Database::connection()->query(
                "SELECT COUNT(*) FROM bookmarks WHERE last_check_status IN ('error', 'timeout')"
            );
            return (int) $stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /** Met à jour l'URL et le host d'un favori et remet le statut de vérification à NULL. */
    public function updateUrl(int $id, int $userId, string $url, string $host): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bookmarks SET url = :url, host = :host, last_check_status = NULL, last_check_at = NULL WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['url' => $url, 'host' => $host, 'id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Supprime plusieurs favoris d'un utilisateur d'un coup. */
    public function deleteMultiple(int $userId, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "DELETE FROM bookmarks WHERE user_id = ? AND id IN ($placeholders)"
        );
        $stmt->execute([$userId, ...$ids]);
        return $stmt->rowCount();
    }

    public function findByUrl(int $userId, string $url, ?int $excludeId = null): array|false
    {
        if ($excludeId !== null) {
            $stmt = Database::connection()->prepare("
                SELECT b.id, b.title, b.host, l.name AS list_name
                FROM bookmarks b
                LEFT JOIN lists l ON l.id = b.list_id
                WHERE b.user_id = :user_id AND b.url = :url AND b.id != :exclude
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId, 'url' => $url, 'exclude' => $excludeId]);
        } else {
            $stmt = Database::connection()->prepare("
                SELECT b.id, b.title, b.host, l.name AS list_name
                FROM bookmarks b
                LEFT JOIN lists l ON l.id = b.list_id
                WHERE b.user_id = :user_id AND b.url = :url
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId, 'url' => $url]);
        }

        return $stmt->fetch();
    }
}
