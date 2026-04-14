<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Classe de base pour tous les tests KT-Start.
 *
 * Avant chaque test, elle :
 *   - crée une base SQLite en mémoire (isolée, jamais partagée entre tests)
 *   - injecte cette connexion dans Database::setConnection()
 *   - crée le schéma complet (toutes tables + colonnes migrées)
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);

        $this->createSchema();
    }

    // ── Schéma complet (tables + colonnes issues des migrations) ─────────────

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                email         TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role          TEXT NOT NULL DEFAULT 'admin',
                created_at    TEXT NOT NULL
            );

            CREATE TABLE lists (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL UNIQUE,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );

            CREATE TABLE folders (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                list_id    INTEGER NOT NULL REFERENCES lists(id),
                parent_id  INTEGER REFERENCES folders(id),
                position   INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );

            CREATE TABLE bookmarks (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                url               TEXT NOT NULL,
                host              TEXT,
                title             TEXT,
                description       TEXT,
                badge_style       TEXT NOT NULL DEFAULT 'deepBlue',
                badge_text        TEXT NOT NULL DEFAULT '',
                tags              TEXT,
                visibility        TEXT NOT NULL DEFAULT 'private',
                list_id           INTEGER REFERENCES lists(id),
                folder_id         INTEGER REFERENCES folders(id),
                user_id           INTEGER NOT NULL REFERENCES users(id),
                position          INTEGER DEFAULT 0,
                created_at        TEXT NOT NULL,
                last_check_status TEXT,
                last_check_at     TEXT,
                last_http_code    INTEGER,
                check_skip        INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ");
    }

    // ── Helpers utilisables dans les sous-classes ────────────────────────────

    /** Insère un utilisateur et retourne son id. */
    protected function createUser(
        string $email = 'test@example.com',
        string $role  = 'admin'
    ): int {
        $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, role, created_at)
             VALUES (:email, :hash, :role, :created_at)'
        )->execute([
            'email'      => $email,
            'hash'       => password_hash('secret', PASSWORD_BCRYPT),
            'role'       => $role,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Insère une liste et retourne son id. */
    protected function createList(string $name, bool $isDefault = false): int
    {
        $this->pdo->prepare(
            'INSERT INTO lists (name, is_default, created_at)
             VALUES (:name, :is_default, :created_at)'
        )->execute([
            'name'       => $name,
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Insère un dossier et retourne son id. */
    protected function createFolder(
        int $userId,
        int $listId,
        string $name = 'Dossier',
        ?int $parentId = null,
        int $position = 0
    ): int {
        $this->pdo->prepare(
            'INSERT INTO folders (name, user_id, list_id, parent_id, position, created_at)
             VALUES (:name, :user_id, :list_id, :parent_id, :position, :created_at)'
        )->execute([
            'name'       => $name,
            'user_id'    => $userId,
            'list_id'    => $listId,
            'parent_id'  => $parentId,
            'position'   => $position,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Insère un favori et retourne son id. */
    protected function createBookmark(int $userId, int $listId, array $overrides = []): int
    {
        // Toutes les colonnes de la table sont déclarées ici avec leur défaut,
        // afin que PDO SQLite ne rejette pas les clés inconnues des overrides.
        $data = array_merge([
            'url'               => 'https://example.com',
            'host'              => 'example.com',
            'title'             => 'Example',
            'description'       => null,
            'badge_style'       => 'deepBlue',
            'badge_text'        => '',
            'tags'              => null,
            'visibility'        => 'private',
            'folder_id'         => null,
            'position'          => 0,
            'created_at'        => '2026-01-01 00:00:00',
            'last_check_status' => null,
            'last_check_at'     => null,
            'last_http_code'    => null,
            'check_skip'        => 0,
        ], $overrides);

        $this->pdo->prepare(
            'INSERT INTO bookmarks
                (url, host, title, description, badge_style, badge_text, tags,
                 visibility, folder_id, list_id, user_id, position, created_at,
                 last_check_status, last_check_at, last_http_code, check_skip)
             VALUES
                (:url, :host, :title, :description, :badge_style, :badge_text, :tags,
                 :visibility, :folder_id, :list_id, :user_id, :position, :created_at,
                 :last_check_status, :last_check_at, :last_http_code, :check_skip)'
        )->execute(array_merge($data, ['list_id' => $listId, 'user_id' => $userId]));

        return (int) $this->pdo->lastInsertId();
    }
}
