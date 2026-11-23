<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Core\RedisClient;

/** Площадки: просмотр для всех, изменение — только админ. Список кэшируется в Redis. */
class LocationController
{
    private const CACHE_KEY = 'cache:locations';
    private const CACHE_TTL = 600; // 10 минут

    /** GET /api/locations */
    public function list(): void
    {
        $redis = RedisClient::get();
        $cached = $redis->get(self::CACHE_KEY);
        if ($cached !== false) {
            header('X-Cache: HIT');
            Http::json(json_decode($cached, true));
            return;
        }
        $rows = array_map([$this, 'toJson'], Database::all('SELECT * FROM locations ORDER BY name'));
        $redis->setex(self::CACHE_KEY, self::CACHE_TTL, json_encode($rows, JSON_UNESCAPED_UNICODE));
        header('X-Cache: MISS');
        Http::json($rows);
    }

    /** GET /api/locations/{id} — площадка + ближайшие мероприятия на ней */
    public function get(int $id): void
    {
        $row = Database::one('SELECT * FROM locations WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpException(404, 'Площадка не найдена');
        }
        $result = $this->toJson($row);
        $result['upcomingEvents'] = array_map(fn($e) => [
            'id'     => (int)$e['id'],
            'name'   => $e['name'],
            'date'   => (new \DateTime($e['date']))->format(DATE_ATOM),
            'status' => $e['status'],
        ], Database::all(
            "SELECT id, name, date, status FROM events
             WHERE location_id = ? AND status IN ('WAIT_START', 'STARTED') ORDER BY date LIMIT 10",
            [$id]
        ));
        Http::json($result);
    }

    /** POST /api/locations */
    public function create(): void
    {
        Auth::requireAdmin();
        $d = $this->validated(Http::body());
        $row = Database::one(
            'INSERT INTO locations (name, address, capacity, description) VALUES (?, ?, ?, ?) RETURNING *',
            [$d['name'], $d['address'], $d['capacity'], $d['description'] ?? null]
        );
        RedisClient::get()->del(self::CACHE_KEY);
        Http::json($this->toJson($row), 201);
    }

    /** PUT /api/locations/{id} */
    public function update(int $id): void
    {
        Auth::requireAdmin();
        $d = $this->validated(Http::body());
        // нельзя уменьшить вместимость ниже числа мест у будущих мероприятий
        $maxUsed = Database::one(
            "SELECT COALESCE(MAX(max_places), 0) AS m FROM events
             WHERE location_id = ? AND status IN ('PENDING', 'WAIT_START')",
            [$id]
        )['m'];
        if ((int)$d['capacity'] < (int)$maxUsed) {
            throw new HttpException(409, "На площадке есть мероприятие на $maxUsed мест — вместимость не может быть меньше");
        }
        $row = Database::one(
            'UPDATE locations SET name = ?, address = ?, capacity = ?, description = ? WHERE id = ? RETURNING *',
            [$d['name'], $d['address'], $d['capacity'], $d['description'] ?? null, $id]
        );
        if (!$row) {
            throw new HttpException(404, 'Площадка не найдена');
        }
        RedisClient::get()->del(self::CACHE_KEY);
        Http::json($this->toJson($row));
    }

    /** DELETE /api/locations/{id} */
    public function delete(int $id): void
    {
        Auth::requireAdmin();
        if (Database::one('SELECT id FROM events WHERE location_id = ? LIMIT 1', [$id])) {
            throw new HttpException(409, 'На площадке есть мероприятия — удалить нельзя');
        }
        if (Database::exec('DELETE FROM locations WHERE id = ?', [$id]) === 0) {
            throw new HttpException(404, 'Площадка не найдена');
        }
        RedisClient::get()->del(self::CACHE_KEY);
        Http::json(['message' => 'Площадка удалена']);
    }

    private function validated(array $d): array
    {
        Http::require($d, ['name', 'address', 'capacity']);
        if (!is_numeric($d['capacity']) || (int)$d['capacity'] < 5) {
            throw new HttpException(400, 'Вместимость — не меньше 5 мест');
        }
        return $d;
    }

    private function toJson(array $r): array
    {
        return [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'address'     => $r['address'],
            'capacity'    => (int)$r['capacity'],
            'description' => $r['description'],
        ];
    }
}
