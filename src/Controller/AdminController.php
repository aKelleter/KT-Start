<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repository\ListRepository;
use App\Repository\UserRepository;
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

        $listRepo = new ListRepository();
        View::render('admin/index', [
            'users'         => (new UserRepository())->findAll(),
            'lists'         => $listRepo->findAllWithCount(),
            'defaultListId' => $listRepo->findDefault(),
            'csrf'          => Csrf::token(),
            'flash'         => Flash::get(),
            'migrationLog'  => $migrationLog,
        ]);
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
