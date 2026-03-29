<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = BASE_PATH . '/templates/' . $template . '.php';
        require BASE_PATH . '/templates/layout.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        return 'public/assets/' . ltrim($path, '/');
    }
}
