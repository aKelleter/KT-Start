<?php
declare(strict_types=1);

namespace Tests\Service;

use App\Core\Database;
use App\Service\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Tests de MigrationService.
 *
 * Chaque test part d'une BD SQLite en mémoire SANS schéma pré-créé,
 * afin de tester la création des tables et l'ajout des colonnes manquantes.
 * On n'étend pas Tests\TestCase car celui-ci crée le schéma complet dans setUp().
 */
final class MigrationServiceTest extends PHPUnitTestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        Database::setConnection($this->pdo);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:name"
        );
        $stmt->execute(['name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->query("PRAGMA table_info(\"$table\")");
        $cols = array_column($stmt->fetchAll(), 'name');
        return in_array($column, $cols, true);
    }

    /** @return string[] */
    private function logStatuses(array $log): array
    {
        return array_column($log, 'status');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Création des tables sur base vide
    // ══════════════════════════════════════════════════════════════════════════

    public function test_run_cree_toutes_les_tables_sur_base_vide(): void
    {
        MigrationService::run();

        foreach (['users', 'lists', 'folders', 'bookmarks', 'settings'] as $table) {
            $this->assertTrue(
                $this->tableExists($table),
                "La table « $table » devrait exister après la migration."
            );
        }
    }

    public function test_run_log_contient_status_created_pour_nouvelles_tables(): void
    {
        $log = MigrationService::run();

        $statuses = $this->logStatuses($log);
        $this->assertContains('created', $statuses);
    }

    public function test_run_log_a_le_bon_format(): void
    {
        $log = MigrationService::run();

        $this->assertNotEmpty($log);
        foreach ($log as $entry) {
            $this->assertArrayHasKey('status',  $entry);
            $this->assertArrayHasKey('message', $entry);
            $this->assertContains($entry['status'], ['ok', 'created', 'added']);
            $this->assertIsString($entry['message']);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Idempotence — deux exécutions consécutives
    // ══════════════════════════════════════════════════════════════════════════

    public function test_run_deux_fois_ne_lance_pas_derreur(): void
    {
        MigrationService::run();

        // Ne doit pas lever d'exception
        $log = MigrationService::run();

        $this->assertNotEmpty($log);
    }

    public function test_run_deuxieme_appel_retourne_uniquement_status_ok(): void
    {
        MigrationService::run();          // premier passage : crée tout
        $log = MigrationService::run();   // deuxième passage : rien à faire

        foreach ($log as $entry) {
            $this->assertSame(
                'ok',
                $entry['status'],
                "Entrée inattendue au second run : {$entry['message']}"
            );
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Colonnes manquantes — ajout sur table pré-existante
    // ══════════════════════════════════════════════════════════════════════════

    public function test_run_ajoute_check_skip_si_colonne_absente(): void
    {
        // Créer bookmarks sans la colonne check_skip (schéma ancienne version)
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TEXT NOT NULL
            );
            CREATE TABLE lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );
            CREATE TABLE folders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                list_id INTEGER NOT NULL,
                parent_id INTEGER,
                position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );
            CREATE TABLE bookmarks (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                url         TEXT NOT NULL,
                user_id     INTEGER NOT NULL,
                created_at  TEXT NOT NULL
            );
        ");

        $this->assertFalse($this->columnExists('bookmarks', 'check_skip'));

        MigrationService::run();

        $this->assertTrue($this->columnExists('bookmarks', 'check_skip'));
    }

    public function test_run_ajoute_last_check_status_si_colonne_absente(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL);
            CREATE TABLE lists (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, is_default INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE folders (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, user_id INTEGER NOT NULL, list_id INTEGER NOT NULL, parent_id INTEGER, position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE bookmarks (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL, user_id INTEGER NOT NULL, created_at TEXT NOT NULL);
        ");

        MigrationService::run();

        $this->assertTrue($this->columnExists('bookmarks', 'last_check_status'));
        $this->assertTrue($this->columnExists('bookmarks', 'last_check_at'));
        $this->assertTrue($this->columnExists('bookmarks', 'last_http_code'));
    }

    public function test_run_ajoute_is_default_sur_lists_si_absent(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL);
            CREATE TABLE lists (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL);
            CREATE TABLE folders (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, user_id INTEGER NOT NULL, list_id INTEGER NOT NULL, parent_id INTEGER, position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE bookmarks (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL, user_id INTEGER NOT NULL, created_at TEXT NOT NULL);
        ");

        $this->assertFalse($this->columnExists('lists', 'is_default'));

        MigrationService::run();

        $this->assertTrue($this->columnExists('lists', 'is_default'));
    }

    public function test_run_log_contient_status_added_pour_colonnes_ajoutees(): void
    {
        // Table bookmarks minimaliste → plusieurs colonnes à ajouter
        $this->pdo->exec("
            CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL);
            CREATE TABLE lists (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, is_default INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE folders (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, user_id INTEGER NOT NULL, list_id INTEGER NOT NULL, parent_id INTEGER, position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
            CREATE TABLE bookmarks (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL, user_id INTEGER NOT NULL, created_at TEXT NOT NULL);
        ");

        $log = MigrationService::run();

        $statuses = $this->logStatuses($log);
        $this->assertContains('added', $statuses);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Préservation des données existantes
    // ══════════════════════════════════════════════════════════════════════════

    public function test_run_ne_supprime_pas_les_donnees_existantes(): void
    {
        // Créer le schéma complet puis insérer des données
        MigrationService::run();

        $this->pdo->exec("
            INSERT INTO users (email, password_hash, role, created_at)
            VALUES ('alice@example.com', 'hash', 'admin', '2026-01-01 00:00:00');

            INSERT INTO lists (name, is_default, created_at)
            VALUES ('Dev', 0, '2026-01-01 00:00:00');
        ");

        // Ré-exécuter la migration
        MigrationService::run();

        $userCount = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $listCount = (int) $this->pdo->query('SELECT COUNT(*) FROM lists')->fetchColumn();

        $this->assertSame(1, $userCount);
        $this->assertSame(1, $listCount);
    }

    public function test_run_preserve_les_valeurs_des_colonnes_existantes(): void
    {
        MigrationService::run();

        $this->pdo->exec("
            INSERT INTO lists (name, is_default, created_at)
            VALUES ('Perso', 1, '2026-01-01 00:00:00');
        ");

        MigrationService::run();

        $isDefault = $this->pdo->query('SELECT is_default FROM lists')->fetchColumn();
        $this->assertSame('1', (string) $isDefault);
    }
}
