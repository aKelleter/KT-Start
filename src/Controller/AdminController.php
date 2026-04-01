<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repository\BookmarkRepository;
use App\Repository\ListRepository;
use App\Repository\SettingsRepository;
use App\Repository\StatsRepository;
use App\Repository\UserRepository;
use App\Service\ImportExportService;
use App\Service\MigrationService;

final class AdminController
{
    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('?action=bookmarks');
        }
    }

    public function index(): void
    {
        $this->requireAdmin();
        $bmRepo = new BookmarkRepository();
        $tags   = $bmRepo->getAllTagsAdmin();
        View::render('admin/index', [
            'userCount'     => count((new UserRepository())->findAll()),
            'listCount'     => count((new ListRepository())->findAll()),
            'tagCount'      => count($tags),
            'deadLinkCount' => $bmRepo->countDeadLinksAll(),
            'flash'         => Flash::get(),
        ]);
    }

    public function usersPage(): void
    {
        $this->requireAdmin();
        View::render('admin/users', [
            'users' => (new UserRepository())->findAll(),
            'csrf'  => Csrf::token(),
            'flash' => Flash::get(),
        ]);
    }

    public function listsPage(): void
    {
        $this->requireAdmin();
        $listRepo = new ListRepository();
        View::render('admin/lists', [
            'lists'         => $listRepo->findAllWithCount(),
            'defaultListId' => $listRepo->findDefault(),
            'csrf'          => Csrf::token(),
            'flash'         => Flash::get(),
        ]);
    }

    public function settingsPage(): void
    {
        $this->requireAdmin();
        $settingsRepo = new SettingsRepository();
        View::render('admin/settings', [
            'settings'    => $settingsRepo->all(),
            'envPerPage'  => $_ENV['BOOKMARKS_PER_PAGE'] ?? null,
            'envProxy'    => $_ENV['CHECK_PROXY'] ?? null,
            'csrf'        => Csrf::token(),
            'flash'       => Flash::get(),
        ]);
    }

    public function backupPage(): void
    {
        $this->requireAdmin();
        $importResult = $_SESSION['_import_result'] ?? null;
        unset($_SESSION['_import_result']);
        View::render('admin/backup', [
            'csrf'         => Csrf::token(),
            'flash'        => Flash::get(),
            'importResult' => $importResult,
        ]);
    }

    public function maintenancePage(): void
    {
        $this->requireAdmin();
        $migrationLog = $_SESSION['_migration_log'] ?? null;
        unset($_SESSION['_migration_log']);
        View::render('admin/maintenance', [
            'csrf'         => Csrf::token(),
            'flash'        => Flash::get(),
            'migrationLog' => $migrationLog,
        ]);
    }

    public function settingUpdate(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_settings');
        }

        $perPage = (int) ($_POST['bookmarks_per_page'] ?? 0);
        if ($perPage < 1 || $perPage > 500) {
            Flash::set('danger', 'Valeur invalide (1–500).');
            Response::redirect('?action=admin_settings');
        }

        $repo = new SettingsRepository();
        $repo->set('bookmarks_per_page', (string) $perPage);
        $repo->set('check_proxy', trim($_POST['check_proxy'] ?? ''));

        Flash::set('success', 'Paramètres enregistrés.');
        Response::redirect('?action=admin_settings');
    }

    public function runMigration(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_maintenance');
        }

        try {
            $_SESSION['_migration_log'] = MigrationService::run();
        } catch (\Throwable $e) {
            $_SESSION['_migration_log'] = [
                ['status' => 'error', 'message' => 'Erreur : ' . $e->getMessage()],
            ];
        }

        Response::redirect('?action=admin_maintenance');
    }

    // ── Tags ─────────────────────────────────────────────────────────────────

    public function tagsPage(): void
    {
        $this->requireAdmin();
        View::render('admin/tags', [
            'tags'  => (new BookmarkRepository())->getAllTagsAdmin(),
            'csrf'  => Csrf::token(),
            'flash' => Flash::get(),
        ]);
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public function statsPage(): void
    {
        $this->requireAdmin();
        $stats = new StatsRepository();
        View::render('admin/stats', [
            'overview'    => $stats->overview(),
            'perUser'     => $stats->perUser(),
            'perList'     => $stats->perList(),
            'perStatus'   => $stats->perLinkStatus(),
            'topTags'     => $stats->topTags(15),
            'perMonth'    => $stats->perMonth(),
            'perBadge'    => $stats->perBadgeStyle(),
            'userCount'   => count((new UserRepository())->findAll()),
        ]);
    }

    public function tagRename(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_tags');
        }

        $old = trim($_POST['old'] ?? '');
        $new = trim($_POST['new'] ?? '');

        if ($old === '' || $new === '') {
            Flash::set('danger', 'Les noms de tag ne peuvent pas être vides.');
            Response::redirect('?action=admin_tags');
        }

        if ($old === $new) {
            Flash::set('danger', 'Le nouveau nom est identique à l\'ancien.');
            Response::redirect('?action=admin_tags');
        }

        $count = (new BookmarkRepository())->renameTag($old, $new);
        Flash::set('success', "Tag « {$old} » renommé en « {$new} » — {$count} favori(s) mis à jour.");
        Response::redirect('?action=admin_tags');
    }

    public function tagDelete(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_tags');
        }

        $tag = trim($_POST['tag'] ?? '');

        if ($tag === '') {
            Flash::set('danger', 'Tag invalide.');
            Response::redirect('?action=admin_tags');
        }

        $count = (new BookmarkRepository())->deleteTag($tag);
        Flash::set('success', "Tag « {$tag} » supprimé de {$count} favori(s).");
        Response::redirect('?action=admin_tags');
    }

    public function tagDeleteUnique(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_tags');
        }

        $count = (new BookmarkRepository())->deleteTagsUsedOnce();

        if ($count === 0) {
            Flash::set('success', 'Aucun tag unique à supprimer.');
        } else {
            Flash::set('success', "{$count} tag(s) uniques supprimés.");
        }

        Response::redirect('?action=admin_tags');
    }

    // ── Import / Export ───────────────────────────────────────────────────────

    public function exportBookmarks(): void
    {
        $this->requireAdmin();

        $data = (new ImportExportService())->export(Auth::id());
        $this->sendJson($data, 'ktstart-bookmarks-' . date('Ymd-His') . '.json');
    }

    public function exportFull(): void
    {
        $this->requireAdmin();

        $data = (new ImportExportService())->exportFull();
        $this->sendJson($data, 'ktstart-backup-' . date('Ymd-His') . '.json');
    }

    private function sendJson(array $data, string $filename): never
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    public function importBookmarks(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_backup');
        }

        $file = $_FILES['import_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Flash::set('danger', 'Aucun fichier reçu ou erreur lors de l\'upload.');
            Response::redirect('?action=admin_backup');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            Flash::set('danger', 'Fichier trop volumineux (10 Mo max).');
            Response::redirect('?action=admin_backup');
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            Flash::set('danger', 'Impossible de lire le fichier.');
            Response::redirect('?action=admin_backup');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            Flash::set('danger', 'Fichier JSON invalide.');
            Response::redirect('?action=admin_backup');
        }

        $fullRestore = isset($_POST['full_restore']) && $_POST['full_restore'] === '1';
        $result      = (new ImportExportService())->import($data, Auth::id(), $fullRestore);

        if ($fullRestore) {
            // La session est obsolète (user_id potentiellement différent après purge)
            $imported = $result['imported'];
            session_destroy();
            session_start();
            Flash::set('success', "Restauration complète effectuée — {$imported} favori(s) restauré(s). Veuillez vous reconnecter.");
            Response::redirect('?action=login');
        }

        $_SESSION['_import_result'] = $result;
        Response::redirect('?action=admin_backup');
    }

    // ── Utilisateurs ─────────────────────────────────────────────────────────

    public function userStore(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_users');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'admin') === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'Email invalide.');
            Response::redirect('?action=admin_users');
        }

        if (strlen($password) < 8) {
            Flash::set('danger', 'Le mot de passe doit faire au moins 8 caractères.');
            Response::redirect('?action=admin_users');
        }

        $repo = new UserRepository();

        if ($repo->emailExists($email)) {
            Flash::set('danger', 'Cet email est déjà utilisé.');
            Response::redirect('?action=admin_users');
        }

        $repo->create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Utilisateur créé.');
        Response::redirect('?action=admin_users');
    }

    public function userUpdate(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_users');
        }

        $id    = (int) ($_POST['id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $role  = ($_POST['role'] ?? 'admin') === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'Email invalide.');
            Response::redirect('?action=admin_users');
        }

        $repo = new UserRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Utilisateur introuvable.');
            Response::redirect('?action=admin_users');
        }

        if ($repo->emailExists($email, $id)) {
            Flash::set('danger', 'Cet email est déjà utilisé.');
            Response::redirect('?action=admin_users');
        }

        $data = ['email' => $email, 'role' => $role];

        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            if (strlen($password) < 8) {
                Flash::set('danger', 'Le mot de passe doit faire au moins 8 caractères.');
                Response::redirect('?action=admin_users');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $repo->update($id, $data);

        if ($id === Auth::id()) {
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role']  = $role;
        }

        Flash::set('success', 'Utilisateur mis à jour.');
        Response::redirect('?action=admin_users');
    }

    public function userDelete(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_users');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new UserRepository();

        if ($id === Auth::id()) {
            Flash::set('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            Response::redirect('?action=admin_users');
        }

        $user   = $repo->findById($id);
        $admins = array_filter($repo->findAll(), fn($u) => $u['role'] === 'admin');

        if ($user && $user['role'] === 'admin' && count($admins) <= 1) {
            Flash::set('danger', 'Impossible de supprimer le dernier administrateur.');
            Response::redirect('?action=admin_users');
        }

        $repo->delete($id);
        Flash::set('success', 'Utilisateur supprimé.');
        Response::redirect('?action=admin_users');
    }

    // ── Listes ───────────────────────────────────────────────────────────────

    public function listStore(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_lists');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Flash::set('danger', 'Le nom est obligatoire.');
            Response::redirect('?action=admin_lists');
        }

        $repo = new ListRepository();
        if ($repo->findByName($name)) {
            Flash::set('danger', 'Cette liste existe déjà.');
            Response::redirect('?action=admin_lists');
        }

        $repo->create($name);
        Flash::set('success', 'Liste créée.');
        Response::redirect('?action=admin_lists');
    }

    public function listRename(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_lists');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            Flash::set('danger', 'Le nom est obligatoire.');
            Response::redirect('?action=admin_lists');
        }

        $repo     = new ListRepository();
        $existing = $repo->findByName($name);

        if ($existing && (int) $existing['id'] !== $id) {
            Flash::set('danger', 'Ce nom est déjà utilisé.');
            Response::redirect('?action=admin_lists');
        }

        $repo->rename($id, $name);
        Flash::set('success', 'Liste renommée.');
        Response::redirect('?action=admin_lists');
    }

    public function listSetDefault(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_lists');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new ListRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Liste introuvable.');
            Response::redirect('?action=admin_lists');
        }

        // Si la liste est déjà la liste par défaut, on la retire
        if ($repo->findDefault() === $id) {
            $repo->clearDefault();
            Flash::set('success', 'Liste par défaut retirée.');
        } else {
            $repo->setDefault($id);
            Flash::set('success', 'Liste par défaut définie.');
        }

        Response::redirect('?action=admin_lists');
    }

    public function listDelete(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin_lists');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new ListRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Liste introuvable.');
            Response::redirect('?action=admin_lists');
        }

        $repo->delete($id);
        Flash::set('success', 'Liste supprimée.');
        Response::redirect('?action=admin_lists');
    }
}
