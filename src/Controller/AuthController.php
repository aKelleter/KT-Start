<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;

final class AuthController
{
    public function login(): void
    {
        View::render('auth/login', [
            'csrf'  => Csrf::token(),
            'flash' => Flash::get(),
        ]);
    }

    public function loginSubmit(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Flash::set('danger', 'Jeton CSRF invalide.');
            Response::redirect('?action=login');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!Auth::attempt($email, $password)) {
            Flash::set('danger', 'Identifiants invalides.');
            Response::redirect('?action=login');
        }

        Response::redirect('?action=bookmarks');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('?action=home');
    }
}
