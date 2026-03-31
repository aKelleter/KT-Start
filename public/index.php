<?php
declare(strict_types=1);

// ── Pre-flight checks ─────────────────────────────────────────────────────────
$_basePath = dirname(__DIR__);

function _ktstart_fatal(string $title, string $body, string $hint): never
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>KT-Start — ' . $title . '</title>'
       . '<style>'
       . '*{box-sizing:border-box;margin:0;padding:0}'
       . 'body{min-height:100vh;display:flex;align-items:center;justify-content:center;'
       .      'background:#f5f5f7;font-family:system-ui,sans-serif;color:#1d1d1f;padding:1.5rem}'
       . '.card{background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.08);'
       .       'padding:2.5rem 2rem;max-width:520px;width:100%}'
       . '.icon{font-size:2.2rem;margin-bottom:1rem}'
       . 'h1{font-size:1.25rem;font-weight:700;margin-bottom:.6rem}'
       . 'p{font-size:.95rem;line-height:1.55;color:#444}'
       . '.hint{margin-top:1.25rem;padding:.9rem 1rem;background:#fff7f0;'
       .       'border-left:3px solid #ff7a00;border-radius:6px;font-size:.88rem;color:#555}'
       . 'code{background:#f0f0f0;border-radius:4px;padding:.1em .4em;'
       .      'font-family:monospace;font-size:.92em;color:#c0392b}'
       . '</style></head><body>'
       . '<div class="card">'
       . '<div class="icon">⚠️</div>'
       . '<h1>' . $title . '</h1>'
       . '<p>' . $body . '</p>'
       . '<div class="hint">' . $hint . '</div>'
       . '</div></body></html>';
    exit;
}

if (!file_exists($_basePath . '/vendor/autoload.php')) {
    _ktstart_fatal(
        'Dépendances manquantes',
        'Le répertoire <code>vendor/</code> est introuvable.',
        'Exécutez <code>composer install</code> à la racine du projet, puis rechargez la page.'
    );
}

if (!file_exists($_basePath . '/.env.local')) {
    _ktstart_fatal(
        'Fichier de configuration manquant',
        'Le fichier <code>.env.local</code> est introuvable.',
        'Copiez <code>.env.local.example</code> en <code>.env.local</code> et ajustez les valeurs selon votre environnement.'
    );
}

require $_basePath . '/config/bootstrap.php';

$_dbRelPath = ltrim((string) ($_ENV['DB_DATABASE'] ?? 'database/app.sqlite'), '/');
if (!file_exists($_basePath . '/' . $_dbRelPath)) {
    _ktstart_fatal(
        'Base de données introuvable',
        'Le fichier <code>' . htmlspecialchars($_dbRelPath) . '</code> est introuvable.',
        'Lancez <code>php scripts/init-db.php</code> pour initialiser la base de données.'
    );
}
unset($_basePath, $_dbRelPath);
// ── End pre-flight ────────────────────────────────────────────────────────────

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\BookmarkController;
use App\Core\Router;

$router = new Router();

$router->get('home',           [BookmarkController::class, 'home'],  true);
$router->get('login',          [AuthController::class,   'login'],  true);
$router->post('login_submit',  [AuthController::class,   'loginSubmit'], true);
$router->get('logout',         [AuthController::class,   'logout']);

$router->get('bookmarks',          [BookmarkController::class, 'index']);
$router->post('bookmark_store',    [BookmarkController::class, 'store']);
$router->post('bookmark_update',   [BookmarkController::class, 'update']);
$router->post('bookmark_delete',   [BookmarkController::class, 'delete']);
$router->get('bookmark_fetch_meta',      [BookmarkController::class, 'fetchMeta']);
$router->get('bookmark_check_duplicate', [BookmarkController::class, 'checkDuplicate']);
$router->post('bookmark_reorder',    [BookmarkController::class, 'reorder']);
$router->get('bookmark_links_report',    [BookmarkController::class, 'linksReport']);
$router->post('bookmark_check_single',  [BookmarkController::class, 'checkSingleLink']);
$router->post('bookmark_reset_status',  [BookmarkController::class, 'resetLinkStatus']);
$router->post('bookmark_delete_dead',      [BookmarkController::class, 'deleteDeadLinks']);
$router->post('bookmark_follow_redirect', [BookmarkController::class, 'followRedirect']);

$router->get('admin',               [AdminController::class, 'index']);
$router->get('admin_users',       [AdminController::class, 'usersPage']);
$router->get('admin_lists',       [AdminController::class, 'listsPage']);
$router->get('admin_settings',    [AdminController::class, 'settingsPage']);
$router->get('admin_backup',      [AdminController::class, 'backupPage']);
$router->get('admin_maintenance', [AdminController::class, 'maintenancePage']);
$router->get('admin_tags',        [AdminController::class, 'tagsPage']);
$router->post('admin_tag_rename',        [AdminController::class, 'tagRename']);
$router->post('admin_tag_delete',        [AdminController::class, 'tagDelete']);
$router->post('admin_tag_delete_unique', [AdminController::class, 'tagDeleteUnique']);
$router->post('admin_user_store',   [AdminController::class, 'userStore']);
$router->post('admin_user_update',  [AdminController::class, 'userUpdate']);
$router->post('admin_user_delete',  [AdminController::class, 'userDelete']);
$router->post('admin_list_store',       [AdminController::class, 'listStore']);
$router->post('admin_list_rename',      [AdminController::class, 'listRename']);
$router->post('admin_list_set_default', [AdminController::class, 'listSetDefault']);
$router->post('admin_list_delete',      [AdminController::class, 'listDelete']);
$router->post('admin_setting_update', [AdminController::class, 'settingUpdate']);
$router->post('admin_run_migration', [AdminController::class, 'runMigration']);
$router->get('admin_export',         [AdminController::class, 'exportBookmarks']);
$router->get('admin_export_full',    [AdminController::class, 'exportFull']);
$router->post('admin_import',        [AdminController::class, 'importBookmarks']);

$action = $_GET['action'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($method, $action);
