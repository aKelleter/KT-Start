<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repository\ListRepository;
use App\Repository\SettingsRepository;
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

        $migrationLog = $_SESSION['_migration_log'] ?? null;
        unset($_SESSION['_migration_log']);

        $importResult = $_SESSION['_import_result'] ?? null;
        unset($_SESSION['_import_result']);

        $listRepo     = new ListRepository();
        $settingsRepo = new SettingsRepository();
        View::render('admin/index', [
            'users'         => (new UserRepository())->findAll(),
            'lists'         => $listRepo->findAllWithCount(),
            'defaultListId' => $listRepo->findDefault(),
            'settings'      => $settingsRepo->all(),
            'envPerPage'    => $_ENV['BOOKMARKS_PER_PAGE'] ?? null,
            'csrf'          => Csrf::token(),
            'flash'         => Flash::get(),
            'migrationLog'  => $migrationLog,
            'importResult'  => $importResult,
        ]);
    }

    public function settingUpdate(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $perPage = (int) ($_POST['bookmarks_per_page'] ?? 0);
        if ($perPage < 1 || $perPage > 500) {
            Flash::set('danger', 'Valeur invalide (1–500).');
            Response::redirect('?action=admin#parametres');
        }

        (new SettingsRepository())->set('bookmarks_per_page', (string) $perPage);

        Flash::set('success', 'Paramètres enregistrés.');
        Response::redirect('?action=admin#parametres');
    }

    public function runMigration(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        try {
            $_SESSION['_migration_log'] = MigrationService::run();
        } catch (\Throwable $e) {
            $_SESSION['_migration_log'] = [
                ['status' => 'error', 'message' => 'Erreur : ' . $e->getMessage()],
            ];
        }

        Response::redirect('?action=admin#maintenance');
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
            Response::redirect('?action=admin#sauvegarde');
        }

        $file = $_FILES['import_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Flash::set('danger', 'Aucun fichier reçu ou erreur lors de l\'upload.');
            Response::redirect('?action=admin#sauvegarde');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            Flash::set('danger', 'Fichier trop volumineux (10 Mo max).');
            Response::redirect('?action=admin#sauvegarde');
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            Flash::set('danger', 'Impossible de lire le fichier.');
            Response::redirect('?action=admin#sauvegarde');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            Flash::set('danger', 'Fichier JSON invalide.');
            Response::redirect('?action=admin#sauvegarde');
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
        Response::redirect('?action=admin#sauvegarde');
    }

    // ── Utilisateurs ─────────────────────────────────────────────────────────

    public function userStore(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'admin') === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'Email invalide.');
            Response::redirect('?action=admin');
        }

        if (strlen($password) < 8) {
            Flash::set('danger', 'Le mot de passe doit faire au moins 8 caractères.');
            Response::redirect('?action=admin');
        }

        $repo = new UserRepository();

        if ($repo->emailExists($email)) {
            Flash::set('danger', 'Cet email est déjà utilisé.');
            Response::redirect('?action=admin');
        }

        $repo->create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        Flash::set('success', 'Utilisateur créé.');
        Response::redirect('?action=admin');
    }

    public function userUpdate(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $id    = (int) ($_POST['id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $role  = ($_POST['role'] ?? 'admin') === 'admin' ? 'admin' : 'user';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'Email invalide.');
            Response::redirect('?action=admin');
        }

        $repo = new UserRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Utilisateur introuvable.');
            Response::redirect('?action=admin');
        }

        if ($repo->emailExists($email, $id)) {
            Flash::set('danger', 'Cet email est déjà utilisé.');
            Response::redirect('?action=admin');
        }

        $data = ['email' => $email, 'role' => $role];

        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            if (strlen($password) < 8) {
                Flash::set('danger', 'Le mot de passe doit faire au moins 8 caractères.');
                Response::redirect('?action=admin');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $repo->update($id, $data);

        if ($id === Auth::id()) {
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role']  = $role;
        }

        Flash::set('success', 'Utilisateur mis à jour.');
        Response::redirect('?action=admin');
    }

    public function userDelete(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new UserRepository();

        if ($id === Auth::id()) {
            Flash::set('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            Response::redirect('?action=admin');
        }

        $user   = $repo->findById($id);
        $admins = array_filter($repo->findAll(), fn($u) => $u['role'] === 'admin');

        if ($user && $user['role'] === 'admin' && count($admins) <= 1) {
            Flash::set('danger', 'Impossible de supprimer le dernier administrateur.');
            Response::redirect('?action=admin');
        }

        $repo->delete($id);
        Flash::set('success', 'Utilisateur supprimé.');
        Response::redirect('?action=admin');
    }

    // ── Listes ───────────────────────────────────────────────────────────────

    public function listStore(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Flash::set('danger', 'Le nom est obligatoire.');
            Response::redirect('?action=admin');
        }

        $repo = new ListRepository();
        if ($repo->findByName($name)) {
            Flash::set('danger', 'Cette liste existe déjà.');
            Response::redirect('?action=admin');
        }

        $repo->create($name);
        Flash::set('success', 'Liste créée.');
        Response::redirect('?action=admin');
    }

    public function listRename(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            Flash::set('danger', 'Le nom est obligatoire.');
            Response::redirect('?action=admin');
        }

        $repo     = new ListRepository();
        $existing = $repo->findByName($name);

        if ($existing && (int) $existing['id'] !== $id) {
            Flash::set('danger', 'Ce nom est déjà utilisé.');
            Response::redirect('?action=admin');
        }

        $repo->rename($id, $name);
        Flash::set('success', 'Liste renommée.');
        Response::redirect('?action=admin');
    }

    public function listSetDefault(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new ListRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Liste introuvable.');
            Response::redirect('?action=admin');
        }

        // Si la liste est déjà la liste par défaut, on la retire
        if ($repo->findDefault() === $id) {
            $repo->clearDefault();
            Flash::set('success', 'Liste par défaut retirée.');
        } else {
            $repo->setDefault($id);
            Flash::set('success', 'Liste par défaut définie.');
        }

        Response::redirect('?action=admin');
    }

    public function listDelete(): void
    {
        $this->requireAdmin();

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=admin');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $repo = new ListRepository();

        if (!$repo->findById($id)) {
            Flash::set('danger', 'Liste introuvable.');
            Response::redirect('?action=admin');
        }

        $repo->delete($id);
        Flash::set('success', 'Liste supprimée.');
        Response::redirect('?action=admin');
    }
}
