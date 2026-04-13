<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Config;
use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = Config::path((string) Config::get('DB_DATABASE', 'database/app.sqlite'));

            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        return self::$pdo;
    }

    /** Injecte une connexion externe — réservé aux tests. */
    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
