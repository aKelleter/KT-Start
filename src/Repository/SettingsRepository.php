<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class SettingsRepository
{
    public function get(string $key, string $default = ''): string
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT value FROM settings WHERE key = :key LIMIT 1'
            );
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch();
            return $row ? (string) $row['value'] : $default;
        } catch (\PDOException) {
            return $default;
        }
    }

    public function set(string $key, string $value): void
    {
        Database::connection()->prepare(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute(['key' => $key, 'value' => $value]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        try {
            $rows = Database::connection()
                ->query('SELECT key, value FROM settings')
                ->fetchAll();
            return array_column($rows, 'value', 'key');
        } catch (\PDOException) {
            return [];
        }
    }
}
