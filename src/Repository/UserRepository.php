<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class UserRepository
{
    public function findAll(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM users ORDER BY created_at DESC'
        );

        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );

        $stmt->execute(['email' => $email]);

        return $stmt->fetch();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM users WHERE email = :email AND id != :id'
            );
            $stmt->execute(['email' => $email, 'id' => $excludeId]);
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM users WHERE email = :email'
            );
            $stmt->execute(['email' => $email]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): void
    {
        $stmt = Database::connection()->prepare("
            INSERT INTO users (email, password_hash, role, created_at)
            VALUES (:email, :password_hash, :role, :created_at)
        ");

        $stmt->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;

        $stmt = Database::connection()->prepare(
            "UPDATE users SET $sets WHERE id = :id"
        );

        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
