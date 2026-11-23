<?php
// Единая точка входа API: все запросы /api/... приходят сюда

use App\Core\HttpException;
use App\Core\Http;
use App\Core\Router;

// Автозагрузка классов: App\Controllers\EventController -> src/Controllers/EventController.php
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// CORS — чтобы фронтенд с другого порта мог обращаться к API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
require __DIR__ . '/../routes/main.php';   // маршруты Backend-1 (пользователи, мероприятия, записи, админ)
require __DIR__ . '/../routes/extra.php';  // маршруты Backend-2 (площадки, отзывы, уведомления, история)

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (HttpException $e) {
    Http::json(['error' => $e->getMessage()], $e->status);
} catch (\Throwable $e) {
    error_log((string)$e);
    Http::json(['error' => 'Внутренняя ошибка сервера'], 500);
}
