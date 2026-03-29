<?php
declare(strict_types=1);

namespace App\Config;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function path(string $relativePath): string
    {
        return BASE_PATH . '/' . ltrim($relativePath, '/');
    }
}
