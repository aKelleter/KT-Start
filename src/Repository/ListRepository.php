<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class ListRepository
{
    public function findAll(): array
    {
        return Database::connection()
            ->query('SELECT * FROM lists ORDER BY name ASC')
            ->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM lists WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function findByName(string $name): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM lists WHERE name = :name LIMIT 1'
        );
        $stmt->execute(['name' => $name]);
        return $stmt->fetch();
    }

    public function create(string $name): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lists (name, created_at) VALUES (:name, :created_at)'
        );
        $stmt->execute(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
        return (int) Database::connection()->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE lists SET name = :name WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public function findAllWithCount(): array
    {
        return Database::connection()
            ->query('
                SELECT l.*, COUNT(b.id) AS bookmark_count
                FROM lists l
                LEFT JOIN bookmarks b ON b.list_id = l.id
                GROUP BY l.id
                ORDER BY l.name ASC
            ')
            ->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM lists WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
