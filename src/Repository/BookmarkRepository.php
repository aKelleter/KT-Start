<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class BookmarkRepository
{
    public function findPublic(?int $listId, ?string $tag, string $sort): array
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

        $orderBy = match ($sort) {
            'title'     => 'LOWER(b.title) ASC',
            'host'      => 'LOWER(b.host) ASC',
            'date_asc'  => 'b.created_at ASC',
            'date_desc' => 'b.created_at DESC',
            default     => 'b.position ASC, b.created_at DESC',
        };

        $sql = "
            SELECT b.*, l.name AS list_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findFiltered(int $userId, ?int $listId, ?string $tag, string $sort): array
    {
        $where  = ['b.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($listId !== null) {
            $where[]          = 'b.list_id = :list_id';
            $params['list_id'] = $listId;
        }

        if ($tag !== null && $tag !== '') {
            $where[]    = "(',' || b.tags || ',' LIKE :tag)";
            $params['tag'] = '%,' . $tag . ',%';
        }

        $orderBy = match ($sort) {
            'title'     => 'LOWER(b.title) ASC',
            'host'      => 'LOWER(b.host) ASC',
            'date_asc'  => 'b.created_at ASC',
            'date_desc' => 'b.created_at DESC',
            default     => 'b.position ASC, b.created_at DESC',
        };

        $sql = "
            SELECT b.*, l.name AS list_name
            FROM bookmarks b
            LEFT JOIN lists l ON l.id = b.list_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

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
                 visibility, list_id, user_id, position, created_at)
            VALUES
                (:url, :host, :title, :description, :badge_style, :badge_text, :tags,
                 :visibility, :list_id, :user_id, :position, :created_at)
        ");
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets      = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
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

    /** @return string[] */
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
                    $tags[$t] = true;
                }
            }
        }

        $tags = array_keys($tags);
        sort($tags);
        return $tags;
    }
}
