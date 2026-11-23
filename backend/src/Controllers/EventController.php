<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Services\EventService;
use App\Services\NotificationService;

class EventController
{
    /** Поля, которые можно менять, и их названия в БД */
    private const EDITABLE = [
        'name'        => 'name',
        'description' => 'description',
        'date'        => 'date',
        'duration'    => 'duration',
        'cost'        => 'cost',
        'maxPlaces'   => 'max_places',
        'locationId'  => 'location_id',
    ];

    /** POST /api/events — создать мероприятие (уходит на подтверждение админу) */
    public function create(): void
    {
        $user = Auth::requireUser();
        $data = Http::body();
        Http::require($data, ['name', 'date', 'duration', 'maxPlaces', 'locationId']);
        $data['cost'] = $data['cost'] ?? 0;
        $this->validate($data);

        $isAdmin = $user['role'] === 'ADMIN';
        $eventId = EventService::transaction(function () use ($data, $user, $isAdmin) {
            $row = Database::one(
                'INSERT INTO events (name, description, owner_id, location_id, date, duration, cost, max_places, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
                [
                    $data['name'], $data['description'] ?? null, $user['id'], $data['locationId'],
                    $data['date'], $data['duration'], $data['cost'], $data['maxPlaces'],
                    $isAdmin ? 'WAIT_START' : 'PENDING',
                ]
            );
            // заявка на создание; мероприятие администратора подтверждается сразу
            Database::exec(
                'INSERT INTO event_requests (event_id, type, status, requested_by, reviewed_by, reviewed_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $row['id'], 'CREATE', $isAdmin ? 'APPROVED' : 'PENDING', $user['id'],
                    $isAdmin ? $user['id'] : null, $isAdmin ? date('c') : null,
                ]
            );
            return (int)$row['id'];
        });

        Http::json(EventService::toJson(EventService::find($eventId, (int)$user['id'])), 201);
    }

    /** GET /api/events/{id} */
    public function get(int $id): void
    {
        $user = Auth::user();
        $event = EventService::find($id, $user['id'] ?? null);
        // неподтверждённые и отклонённые видят только владелец и админ
        if (in_array($event['status'], ['PENDING', 'REJECTED'], true) && !$this->canManage($event, $user)) {
            throw new HttpException(404, 'Мероприятие не найдено');
        }
        Http::json(EventService::toJson($event));
    }

    /** PUT /api/events/{id} — изменить; участники получают уведомление со списком изменений */
    public function update(int $id): void
    {
        $user = Auth::requireUser();
        $data = array_intersect_key(Http::body(), self::EDITABLE);
        if (!$data) {
            throw new HttpException(400, 'Нет полей для изменения');
        }

        EventService::transaction(function () use ($id, $user, $data) {
            $event = EventService::lock($id);
            if (!$this->canManage($event, $user)) {
                throw new HttpException(403, 'Изменять мероприятие может только владелец или администратор');
            }
            if (!in_array($event['status'], ['PENDING', 'WAIT_START'], true)) {
                throw new HttpException(409, 'Можно изменить только мероприятие, которое ещё не началось');
            }
            $merged = $this->mergeForValidation($event, $data);
            $this->validate($merged);
            if ((int)$merged['maxPlaces'] < EventService::activeCount($id)) {
                throw new HttpException(409, 'Мест не может быть меньше, чем уже записано людей');
            }

            $changes = [];
            $sets = [];
            $params = [];
            foreach ($data as $field => $value) {
                $column = self::EDITABLE[$field];
                $old = $event[$column];
                $same = $field === 'date'
                    ? strtotime((string)$old) === strtotime((string)$value)
                    : (string)$old === (string)$value || (is_numeric($old) && is_numeric($value) && (float)$old === (float)$value);
                if ($same) {
                    continue;
                }
                $changes[] = ['field' => $field, 'oldValue' => $old, 'newValue' => $value];
                $sets[] = "$column = ?";
                $params[] = $value;
            }
            if (!$changes) {
                return;
            }
            $params[] = $id;
            Database::exec('UPDATE events SET ' . implode(', ', $sets) . ', updated_at = now() WHERE id = ?', $params);

            if ($event['status'] === 'WAIT_START') {
                NotificationService::notify(
                    NotificationService::participants($id),
                    $id,
                    'EVENT_UPDATED',
                    "Мероприятие «{$event['name']}» изменено",
                    $changes
                );
                EventService::promoteWaitlist($id); // если мест стало больше
            }
        });

        Http::json(EventService::toJson(EventService::find($id, (int)$user['id'])));
    }

    /**
     * DELETE /api/events/{id} — отмена.
     * Админ отменяет сразу. Владелец: неподтверждённое — сразу, подтверждённое — через заявку админу.
     */
    public function cancel(int $id): void
    {
        $user = Auth::requireUser();
        $result = EventService::transaction(function () use ($id, $user) {
            $event = EventService::lock($id);
            if (!$this->canManage($event, $user)) {
                throw new HttpException(403, 'Отменить мероприятие может только владелец или администратор');
            }

            if ($event['status'] === 'PENDING') {
                Database::exec("UPDATE events SET status = 'CANCELLED', updated_at = now() WHERE id = ?", [$id]);
                Database::exec(
                    "UPDATE event_requests SET status = 'REJECTED', comment = 'Отозвано владельцем', reviewed_at = now()
                     WHERE event_id = ? AND status = 'PENDING'",
                    [$id]
                );
                return ['status' => 200, 'message' => 'Заявка на создание отозвана'];
            }
            if ($event['status'] !== 'WAIT_START') {
                throw new HttpException(409, 'Отменить можно только мероприятие, которое ещё не началось');
            }
            if ($user['role'] === 'ADMIN') {
                EventService::cancelEvent($id);
                return ['status' => 200, 'message' => 'Мероприятие отменено'];
            }
            $pending = Database::one(
                "SELECT id FROM event_requests WHERE event_id = ? AND type = 'CANCEL' AND status = 'PENDING'",
                [$id]
            );
            if ($pending) {
                throw new HttpException(409, 'Заявка на отмену уже отправлена');
            }
            Database::exec(
                "INSERT INTO event_requests (event_id, type, requested_by) VALUES (?, 'CANCEL', ?)",
                [$id, $user['id']]
            );
            return ['status' => 202, 'message' => 'Заявка на отмену отправлена администратору'];
        });

        Http::json(['message' => $result['message']], $result['status']);
    }

    /** POST /api/events/search — поиск с фильтрами и страницами (все фильтры необязательны) */
    public function search(): void
    {
        $user = Auth::user();
        $f = Http::body();
        [$page, $size] = Http::paging($f);

        $where = ["e.status NOT IN ('PENDING', 'REJECTED')"];
        $params = ['uid' => $user['id'] ?? 0];
        $filters = [
            'name'            => ['e.name ILIKE :name', fn($v) => "%$v%"],
            'dateStartAfter'  => ['e.date >= :dateStartAfter', null],
            'dateStartBefore' => ['e.date <= :dateStartBefore', null],
            'costMin'         => ['e.cost >= :costMin', null],
            'costMax'         => ['e.cost <= :costMax', null],
            'locationId'      => ['e.location_id = :locationId', null],
            'status'          => ['e.status = :status', null],
            'placeMin'        => ['e.max_places >= :placeMin', null],
            'durationMin'     => ['e.duration >= :durationMin', null],
            'durationMax'     => ['e.duration <= :durationMax', null],
        ];
        foreach ($filters as $key => [$condition, $transform]) {
            if (isset($f[$key]) && $f[$key] !== '') {
                $where[] = $condition;
                $params[$key] = $transform ? $transform($f[$key]) : $f[$key];
            }
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $countParams = $params;
        unset($countParams['uid']);
        $total = (int)Database::one('SELECT COUNT(*) AS c FROM events e' . $whereSql, $countParams)['c'];

        $offset = $page * $size;
        $rows = Database::all(
            EventService::BASE_SELECT . $whereSql . " ORDER BY e.date LIMIT $size OFFSET $offset",
            $params
        );

        Http::json([
            'items' => array_map([EventService::class, 'toJson'], $rows),
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
        ]);
    }

    /** GET /api/events/my — мероприятия, созданные текущим пользователем (все статусы) */
    public function my(): void
    {
        $user = Auth::requireUser();
        $rows = Database::all(
            EventService::BASE_SELECT . ' WHERE e.owner_id = :owner ORDER BY e.date DESC',
            ['uid' => $user['id'], 'owner' => $user['id']]
        );
        Http::json(array_map([EventService::class, 'toJson'], $rows));
    }

    // ---------- вспомогательные ----------

    private function canManage(array $event, ?array $user): bool
    {
        return $user && ($user['role'] === 'ADMIN' || (int)$event['owner_id'] === (int)$user['id']);
    }

    /** Подставить текущие значения для полей, которые не меняются (для проверки) */
    private function mergeForValidation(array $event, array $data): array
    {
        $merged = [];
        foreach (self::EDITABLE as $field => $column) {
            $merged[$field] = $data[$field] ?? $event[$column];
        }
        return $merged;
    }

    private function validate(array $d): void
    {
        if (mb_strlen(trim((string)$d['name'])) < 3) {
            throw new HttpException(400, 'Название — минимум 3 символа');
        }
        $ts = strtotime((string)$d['date']);
        if ($ts === false) {
            throw new HttpException(400, 'Неверный формат даты');
        }
        if ($ts <= time()) {
            throw new HttpException(400, 'Дата мероприятия должна быть в будущем');
        }
        if (!is_numeric($d['duration']) || (int)$d['duration'] < 10) {
            throw new HttpException(400, 'Длительность — не меньше 10 минут');
        }
        if (!is_numeric($d['cost']) || (float)$d['cost'] < 0) {
            throw new HttpException(400, 'Цена не может быть отрицательной');
        }
        if (!is_numeric($d['maxPlaces']) || (int)$d['maxPlaces'] < 1) {
            throw new HttpException(400, 'Мест должно быть хотя бы 1');
        }
        $location = Database::one('SELECT capacity FROM locations WHERE id = ?', [(int)$d['locationId']]);
        if (!$location) {
            throw new HttpException(400, 'Площадка не найдена');
        }
        if ((int)$d['maxPlaces'] > (int)$location['capacity']) {
            throw new HttpException(400, "Мест больше, чем вмещает площадка ({$location['capacity']})");
        }
    }
}
