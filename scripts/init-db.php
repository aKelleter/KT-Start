<?php
declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        email         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role          TEXT NOT NULL DEFAULT 'admin',
        created_at    TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS lists (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS bookmarks (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        url         TEXT NOT NULL,
        host        TEXT,
        title       TEXT,
        description TEXT,
        badge_style TEXT NOT NULL DEFAULT 'primary',
        badge_text  TEXT NOT NULL DEFAULT '',
        tags        TEXT,
        visibility  TEXT NOT NULL DEFAULT 'private',
        list_id     INTEGER REFERENCES lists(id),
        user_id     INTEGER NOT NULL REFERENCES users(id),
        position    INTEGER DEFAULT 0,
        created_at  TEXT NOT NULL
    );
");

echo "Tables créées.\n";

// Créer un utilisateur admin par défaut si la table est vide
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

if ($count === 0) {
    $email    = 'admin@example.com';
    $password = 'changeme';
    $hash     = password_hash($password, PASSWORD_BCRYPT);
    $now      = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, role, created_at)
        VALUES (:email, :password_hash, :role, :created_at)
    ");
    $stmt->execute([
        'email'         => $email,
        'password_hash' => $hash,
        'role'          => 'admin',
        'created_at'    => $now,
    ]);

    echo "Utilisateur admin créé : $email / $password\n";
    echo "⚠  Changez le mot de passe immédiatement !\n";
} else {
    echo "Des utilisateurs existent déjà, aucun admin créé.\n";
}

echo "Initialisation terminée.\n";
