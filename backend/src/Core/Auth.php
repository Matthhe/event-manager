<?php
namespace App\Core;

/**
 * Авторизация по токену: заголовок "Authorization: Bearer <token>".
 * Токен хранится в Redis с временем жизни 24 часа.
 */
class Auth
{
    public const TOKEN_TTL = 86400;
    private static ?array $user = null;

    public static function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        RedisClient::get()->setex("auth:token:$token", self::TOKEN_TTL, (string)$userId);
        return $token;
    }

    public static function revokeToken(): void
    {
        $token = self::tokenFromHeader();
        if ($token) {
            RedisClient::get()->del("auth:token:$token");
        }
    }

    private static function tokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            $header = array_change_key_case(getallheaders())['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(\S+)$/', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Текущий пользователь или null, если не вошёл */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $token = self::tokenFromHeader();
        if (!$token) {
            return null;
        }
        $userId = RedisClient::get()->get("auth:token:$token");
        if (!$userId) {
            return null;
        }
        self::$user = Database::one('SELECT id, login, name, age, role FROM users WHERE id = ?', [(int)$userId]);
        return self::$user;
    }

    /** Требует вход; возвращает пользователя */
    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpException(401, 'Нужно войти в систему');
        }
        return $user;
    }

    /** Требует роль ADMIN */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'ADMIN') {
            throw new HttpException(403, 'Доступно только администратору');
        }
        return $user;
    }
}
