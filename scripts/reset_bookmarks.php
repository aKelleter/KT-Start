<?php
declare(strict_types=1);

/**
 * Vide les tables bookmarks et lists, réinitialise les auto-increments.
 * Usage : php scripts/reset_bookmarks.php
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec('PRAGMA foreign_keys = OFF');

$pdo->exec('DELETE FROM bookmarks');
$pdo->exec('DELETE FROM sqlite_sequence WHERE name = "bookmarks"');

$pdo->exec('DELETE FROM lists');
$pdo->exec('DELETE FROM sqlite_sequence WHERE name = "lists"');

$pdo->exec('PRAGMA foreign_keys = ON');

echo "Tables bookmarks et lists vidées, auto-increments remis à 1.\n";
