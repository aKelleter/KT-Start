<?php
declare(strict_types=1);

use Dotenv\Dotenv;

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

if (file_exists(BASE_PATH . '/.env.local')) {
    $dotenvLocal = Dotenv::createMutable(BASE_PATH, '.env.local');
    $dotenvLocal->load();
}

$appDebug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

if ($appDebug) {
    ini_set('display_errors', '1');
    ini_set('html_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
    $errorLog = BASE_PATH . '/storage/log/php_errors.log';
    if (!is_dir(dirname($errorLog))) {
        mkdir(dirname($errorLog), 0775, true);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLog);
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('ktstart_session');
    session_start();
}
