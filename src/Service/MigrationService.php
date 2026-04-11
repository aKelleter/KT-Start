<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use PDO;

/**
 * Migrations idempotentes : crée les tables manquantes, ajoute les colonnes absentes.
 * Sans effet sur les données existantes.
 */
final class MigrationService
{
    /** Schéma attendu : table → colonnes avec leur DDL */
    private const SCHEMA = [
        'settings' => [
            'create' => "
                CREATE TABLE IF NOT EXISTS settings (
                    key   TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                )
            ",
            'columns' => [],
        ],
        'users' => [
            'create' => "
                CREATE TABLE IF NOT EXISTS users (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    email         TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role          TEXT NOT NULL DEFAULT 'admin',
                    created_at    TEXT NOT NULL
                )
            ",
            'columns' => [
                'role'       => "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'",
                'created_at' => "ALTER TABLE users ADD COLUMN created_at TEXT NOT NULL DEFAULT ''",
            ],
        ],
        'lists' => [
            'create' => "
                CREATE TABLE IF NOT EXISTS lists (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    name       TEXT NOT NULL UNIQUE,
                    is_default INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL
                )
            ",
            'columns' => [
                'is_default' => "ALTER TABLE lists ADD COLUMN is_default INTEGER NOT NULL DEFAULT 0",
                'created_at' => "ALTER TABLE lists ADD COLUMN created_at TEXT NOT NULL DEFAULT ''",
            ],
        ],
        'folders' => [
            'create' => "
                CREATE TABLE IF NOT EXISTS folders (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    name       TEXT NOT NULL,
                    user_id    INTEGER NOT NULL REFERENCES users(id),
                    list_id    INTEGER NOT NULL REFERENCES lists(id),
                    parent_id  INTEGER REFERENCES folders(id),
                    position   INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL
                )
            ",
            'columns' => [
                'user_id'    => 'ALTER TABLE folders ADD COLUMN user_id INTEGER NOT NULL DEFAULT 0',
                'list_id'    => 'ALTER TABLE folders ADD COLUMN list_id INTEGER NOT NULL DEFAULT 0',
                'parent_id'  => 'ALTER TABLE folders ADD COLUMN parent_id INTEGER REFERENCES folders(id)',
                'position'   => 'ALTER TABLE folders ADD COLUMN position INTEGER NOT NULL DEFAULT 0',
                'created_at' => "ALTER TABLE folders ADD COLUMN created_at TEXT NOT NULL DEFAULT ''",
            ],
        ],
        'bookmarks' => [
            'create' => "
                CREATE TABLE IF NOT EXISTS bookmarks (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    url         TEXT NOT NULL,
                    host        TEXT,
                    title       TEXT,
                    description TEXT,
                    badge_style TEXT NOT NULL DEFAULT 'deepBlue',
                    badge_text  TEXT NOT NULL DEFAULT '',
                    tags        TEXT,
                    visibility  TEXT NOT NULL DEFAULT 'private',
                    list_id     INTEGER REFERENCES lists(id),
                    folder_id   INTEGER REFERENCES folders(id),
                    user_id     INTEGER NOT NULL REFERENCES users(id),
                    position    INTEGER DEFAULT 0,
                    created_at  TEXT NOT NULL,
                    last_check_status TEXT,
                    last_check_at     TEXT,
                    last_http_code    INTEGER,
                    check_skip        INTEGER NOT NULL DEFAULT 0
                )
            ",
            'columns' => [
                'host'        => 'ALTER TABLE bookmarks ADD COLUMN host TEXT',
                'title'       => 'ALTER TABLE bookmarks ADD COLUMN title TEXT',
                'description' => 'ALTER TABLE bookmarks ADD COLUMN description TEXT',
                'badge_style' => "ALTER TABLE bookmarks ADD COLUMN badge_style TEXT NOT NULL DEFAULT 'deepBlue'",
                'badge_text'  => "ALTER TABLE bookmarks ADD COLUMN badge_text TEXT NOT NULL DEFAULT ''",
                'tags'        => 'ALTER TABLE bookmarks ADD COLUMN tags TEXT',
                'visibility'  => "ALTER TABLE bookmarks ADD COLUMN visibility TEXT NOT NULL DEFAULT 'private'",
                'list_id'     => 'ALTER TABLE bookmarks ADD COLUMN list_id INTEGER REFERENCES lists(id)',
                'folder_id'         => 'ALTER TABLE bookmarks ADD COLUMN folder_id INTEGER REFERENCES folders(id)',
                'position'          => 'ALTER TABLE bookmarks ADD COLUMN position INTEGER DEFAULT 0',
                'last_check_status' => 'ALTER TABLE bookmarks ADD COLUMN last_check_status TEXT',
                'last_check_at'     => 'ALTER TABLE bookmarks ADD COLUMN last_check_at TEXT',
                'last_http_code'    => 'ALTER TABLE bookmarks ADD COLUMN last_http_code INTEGER',
                'check_skip'        => 'ALTER TABLE bookmarks ADD COLUMN check_skip INTEGER NOT NULL DEFAULT 0',
            ],
        ],
    ];

    /**
     * @return list<array{status: 'ok'|'created'|'added', message: string}>
     */
    public static function run(): array
    {
        $pdo = Database::connection();
        $log = [];

        foreach (self::SCHEMA as $table => $def) {
            // 1. Créer la table si elle n'existe pas
            $existed = self::tableExists($pdo, $table);
            $pdo->exec($def['create']);

            if (!$existed) {
                $log[] = ['status' => 'created', 'message' => "Table « $table » créée."];
            } else {
                $log[] = ['status' => 'ok', 'message' => "Table « $table » : présente."];
            }

            // 2. Ajouter les colonnes manquantes
            $existing = self::getColumns($pdo, $table);

            foreach ($def['columns'] as $column => $ddl) {
                if (!in_array($column, $existing, true)) {
                    $pdo->exec($ddl);
                    $log[] = ['status' => 'added', 'message' => "Colonne « $table.$column » ajoutée."];
                } else {
                    $log[] = ['status' => 'ok', 'message' => "Colonne « $table.$column » : présente."];
                }
            }
        }

        return $log;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:name"
        );
        $stmt->execute(['name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return string[] */
    private static function getColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("PRAGMA table_info(\"$table\")");
        return array_column($stmt->fetchAll(), 'name');
    }
}
