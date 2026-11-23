<?php
namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/** Общая логика мероприятий: выборка с подсчётами, отмена, продвижение списка ожидания */
class EventService
{
    /** SELECT с площадкой, владельцем, числом мест, рейтингом и статусом записи текущего пользователя */
    public const BASE_SELECT = "
        SELECT e.*, l.name AS location_name, l.address AS location_address, u.name AS owner_name,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'ACTIVE')   AS occupied_places,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'WAITLIST') AS waitlist_count,
            (SELECT ROUND(AVG(rv.rating), 1) FROM reviews rv WHERE rv.event_id = e.id) AS rating,
            (SELECT COUNT(*) FROM reviews rv WHERE rv.event_id = e.id) AS reviews_count,
            (SELECT r.status FROM registrations r WHERE r.event_id = e.id AND r.user_id = :uid) AS my_status,
            (SELECT er.type FROM event_requests er WHERE er.event_id = e.id AND er.status = 'PENDING' LIMIT 1) AS pending_request
        FROM events e
        JOIN locations l ON l.id = e.location_id
        JOIN users u ON u.id = e.owner_id";

    /** Найти мероприятие или 404 */
    public static function find(int $id, ?int $userId = null): array
    {
        $row = Database::one(self::BASE_SELECT . ' WHERE e.id = :id', ['id' => $id, 'uid' => $userId ?? 0]);
        if (!$row) {
            throw new HttpException(404, 'Мероприятие не найдено');
        }
        return $row;
    }

    /** Преобразовать строку БД в JSON для фронтенда (camelCase) */
    public static function toJson(array $r): array
    {
        return [
            'id'                   => (int)$r['id'],
            'name'                 => $r['name'],
            'description'          => $r['description'],
            'ownerId'              => (int)$r['owner_id'],
            'ownerName'            => $r['owner_name'],
            'locationId'           => (int)$r['location_id'],
            'locationName'         => $r['location_name'],
            'locationAddress'      => $r['location_address'],
            'date'                 => (new \DateTime($r['date']))->format(DATE_ATOM),
            'duration'             => (int)$r['duration'],
            'cost'                 => (float)$r['cost'],
            'maxPlaces'            => (int)$r['max_places'],
            'occupiedPlaces'       => (int)$r['occupied_places'],
            'waitlistCount'        => (int)$r['waitlist_count'],
            'status'               => $r['status'],
            'rating'               => $r['rating'] !== null ? (float)$r['rating'] : null,
            'reviewsCount'         => (int)$r['reviews_count'],
            'myRegistrationStatus' => $r['my_status'],
            'pendingRequest'       => $r['pending_request'],
        ];
    }

    /** Заблокировать строку мероприятия до конца транзакции (защита от одновременной записи) */
    public static function lock(int $id): array
    {
        $row = Database::one('SELECT * FROM events WHERE id = ? FOR UPDATE', [$id]);
        if (!$row) {
            throw new HttpException(404, 'Мероприятие не найдено');
        }
        return $row;
    }

    public static function activeCount(int $eventId): int
    {
        return (int)Database::one(
            "SELECT COUNT(*) AS c FROM registrations WHERE event_id = ? AND status = 'ACTIVE'",
            [$eventId]
        )['c'];
    }

    /** Отменить мероприятие и уведомить всех участников */
    public static function cancelEvent(int $eventId): void
    {
        $event = self::lock($eventId);
        Database::exec("UPDATE events SET status = 'CANCELLED', updated_at = now() WHERE id = ?", [$eventId]);
        NotificationService::notify(
            NotificationService::participants($eventId),
            $eventId,
            'EVENT_CANCELLED',
            "Мероприятие «{$event['name']}» отменено",
            [['field' => 'status', 'oldValue' => $event['status'], 'newValue' => 'CANCELLED']]
        );
    }

    /**
     * Пока есть свободные места — переводим первых из списка ожидания в участники.
     * Вызывать внутри транзакции после lock().
     */
    public static function promoteWaitlist(int $eventId): void
    {
        $event = Database::one('SELECT name, max_places FROM events WHERE id = ?', [$eventId]);
        $free = (int)$event['max_places'] - self::activeCount($eventId);
        if ($free <= 0) {
            return;
        }
        $next = Database::all(
            "SELECT id, user_id FROM registrations WHERE event_id = ? AND status = 'WAITLIST'
             ORDER BY updated_at, id LIMIT $free",
            [$eventId]
        );
        foreach ($next as $reg) {
            Database::exec("UPDATE registrations SET status = 'ACTIVE', updated_at = now() WHERE id = ?", [$reg['id']]);
            NotificationService::notify(
                [$reg['user_id']],
                $eventId,
                'WAITLIST_PROMOTED',
                "Освободилось место на «{$event['name']}» — вы записаны"
            );
        }
    }

    /** Выполнить функцию в транзакции */
    public static function transaction(callable $fn): mixed
    {
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
