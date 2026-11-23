<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Core\RedisClient;

class UserController
{
    private const MAX_FAILS = 5;       // после 5 неудачных попыток...
    private const BLOCK_SECONDS = 900; // ...блокируем вход на 15 минут

    /** POST /api/users — регистрация */
    public function register(): void
    {
        $data = Http::body();
        Http::require($data, ['login', 'password', 'name']);

        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['login'])) {
            throw new HttpException(400, 'Логин: 3–50 символов, латиница, цифры, _');
        }
        if (mb_strlen($data['password']) < 6) {
            throw new HttpException(400, 'Пароль должен быть не короче 6 символов');
        }
        if (Database::one('SELECT id FROM users WHERE login = ?', [$data['login']])) {
            throw new HttpException(409, 'Такой логин уже занят');
        }

        $user = Database::one(
            'INSERT INTO users (login, password_hash, name, age) VALUES (?, ?, ?, ?)
             RETURNING id, login, name, age, role',
            [$data['login'], password_hash($data['password'], PASSWORD_DEFAULT), $data['name'], $data['age'] ?? null]
        );
        Http::json($user, 201);
    }

    /** POST /api/users/auth — вход, возвращает токен */
    public function login(): void
    {
        $data = Http::body();
        Http::require($data, ['login', 'password']);

        $redis = RedisClient::get();
        $failKey = 'auth:fail:' . $data['login'];
        if ((int)$redis->get($failKey) >= self::MAX_FAILS) {
            throw new HttpException(429, 'Слишком много попыток входа. Попробуйте через 15 минут');
        }

        $user = Database::one('SELECT * FROM users WHERE login = ?', [$data['login']]);
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            $redis->incr($failKey);
            $redis->expire($failKey, self::BLOCK_SECONDS);
            throw new HttpException(401, 'Неверный логин или пароль');
        }

        $redis->del($failKey);
        unset($user['password_hash']);
        Http::json(['token' => Auth::issueToken((int)$user['id']), 'user' => $user]);
    }

    /** POST /api/users/logout */
    public function logout(): void
    {
        Auth::revokeToken();
        Http::json(['ok' => true]);
    }

    /** GET /api/users/me */
    public function me(): void
    {
        Http::json(Auth::requireUser());
    }
}
