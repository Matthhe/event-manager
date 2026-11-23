<?php
namespace App\Core;

use Redis;

/**
 * Подключение к Redis.
 * Что храним в Redis:
 *   auth:token:{token}      — id пользователя по токену (TTL 24 ч)
 *   auth:fail:{login}       — счётчик неудачных входов (TTL 15 мин)
 *   notif:unread:{userId}   — число непрочитанных уведомлений
 *   cache:locations         — кэш списка площадок
 */
class RedisClient
{
    private static ?Redis $redis = null;

    public static function get(): Redis
    {
        if (self::$redis === null) {
            self::$redis = new Redis();
            self::$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
        }
        return self::$redis;
    }
}
