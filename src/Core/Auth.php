<?php
declare(strict_types=1);

namespace App\Core;

use App\Repository\UserRepository;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'    => (int) $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];

        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (($_SESSION['user']['role'] ?? '') === 'admin');
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
