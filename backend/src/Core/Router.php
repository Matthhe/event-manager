<?php
namespace App\Core;

/**
 * Простой роутер: $router->get('/api/events/{id}', [EventController::class, 'get']);
 * {id} — только цифры, передаётся в метод контроллера как аргумент.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void   { $this->add('POST', $path, $handler); }
    public function put(string $path, array $handler): void    { $this->add('PUT', $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{\w+\}#', '(\d+)', $path) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/') ?: '/';
        $pathFound = false;
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (preg_match($regex, $path, $matches)) {
                $pathFound = true;
                if ($m !== $method) {
                    continue;
                }
                array_shift($matches);
                [$class, $action] = $handler;
                (new $class())->$action(...array_map('intval', $matches));
                return;
            }
        }
        throw $pathFound
            ? new HttpException(405, 'Метод не поддерживается')
            : new HttpException(404, 'Маршрут не найден');
    }
}
