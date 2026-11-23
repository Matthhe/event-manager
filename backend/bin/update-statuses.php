<?php
// Запуск: php bin/update-statuses.php  (в docker-compose — сервис scheduler, раз в минуту)

spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$result = App\Services\EventStatusUpdater::run();
echo date('Y-m-d H:i:s') . ' ' . json_encode($result) . PHP_EOL;
