<?php
namespace App\Core;

use PDO;

/** Одно подключение к PostgreSQL на весь запрос */
class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '5432',
                getenv('DB_NAME') ?: 'eventhub'
            );
            self::$pdo = new PDO($dsn, getenv('DB_USER') ?: 'eventhub', getenv('DB_PASS') ?: 'eventhub', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }

    /** Выполнить запрос с параметрами и вернуть все строки */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Вернуть одну строку или null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** INSERT/UPDATE/DELETE; возвращает число затронутых строк */
    public static function exec(string $sql, array $params = []): int
    {
        $st = self::get()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
