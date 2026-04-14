<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $action, callable|array $handler, bool $public = false): void
    {
        $this->routes['GET'][$action] = [
            'handler' => $handler,
            'public'  => $public,
        ];
    }

    public function post(string $action, callable|array $handler, bool $public = false): void
    {
        $this->routes['POST'][$action] = [
            'handler' => $handler,
            'public'  => $public,
        ];
    }

    public function dispatch(string $method, string $action): void
    {
        $route = $this->routes[$method][$action] ?? null;

        if (!$route) {
            http_response_code(404);
            View::render('errors/404', ['requestedAction' => $action]);
            return;
        }

        if (($route['public'] ?? false) === false && !Auth::check()) {
            Response::redirect('?action=home');
        }

        $handler = $route['handler'];

        if (is_array($handler)) {
            [$class, $method] = $handler;
            (new $class())->{$method}();
            return;
        }

        $handler();
    }
}
