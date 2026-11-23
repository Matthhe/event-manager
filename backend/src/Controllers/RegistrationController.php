<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Services\EventService;

/** Запись на мероприятие, отмена записи и список ожидания */
class RegistrationController
{
    /** POST /api/events/registrations/{eventId} — записаться; если мест нет — встать в список ожидания */
    public function register(int $eventId): void
    {
        $user = Auth::requireUser();

        $status = EventService::transaction(function () use ($eventId, $user) {
            $event = EventService::lock($eventId);
            if ($event['status'] !== 'WAIT_START') {
                throw new HttpException(409, 'Запись возможна только на мероприятие, которое ещё не началось');
            }

            $existing = Database::one(
                'SELECT id, status FROM registrations WHERE event_id = ? AND user_id = ?',
                [$eventId, $user['id']]
            );
            if ($existing && $existing['status'] !== 'CANCELLED') {
                throw new HttpException(409, $existing['status'] === 'ACTIVE'
                    ? 'Вы уже записаны на это мероприятие'
                    : 'Вы уже в списке ожидания');
            }

            $hasPlace = EventService::activeCount($eventId) < (int)$event['max_places'];
            $newStatus = $hasPlace ? 'ACTIVE' : 'WAITLIST';

            if ($existing) { // раньше отменял запись — восстанавливаем
                Database::exec(
                    'UPDATE registrations SET status = ?, updated_at = now() WHERE id = ?',
                    [$newStatus, $existing['id']]
                );
            } else {
                Database::exec(
                    'INSERT INTO registrations (event_id, user_id, status) VALUES (?, ?, ?)',
                    [$eventId, $user['id'], $newStatus]
                );
            }
            return $newStatus;
        });

        $result = [
            'status'  => $status,
            'message' => $status === 'ACTIVE' ? 'Вы записаны' : 'Мест нет — вы добавлены в список ожидания',
        ];
        if ($status === 'WAITLIST') {
            $result['position'] = $this->position($eventId, (int)$user['id']);
        }
        Http::json($result, 201);
    }

    /** DELETE /api/events/registrations/cancel/{eventId} — отменить запись или выйти из списка ожидания */
    public function cancel(int $eventId): void
    {
        $user = Auth::requireUser();

        EventService::transaction(function () use ($eventId, $user) {
            $event = EventService::lock($eventId);
            if ($event['status'] !== 'WAIT_START') {
                throw new HttpException(409, 'Отменить запись можно только до начала мероприятия');
            }
            $reg = Database::one(
                "SELECT id, status FROM registrations WHERE event_id = ? AND user_id = ? AND status IN ('ACTIVE', 'WAITLIST')",
                [$eventId, $user['id']]
            );
            if (!$reg) {
                throw new HttpException(404, 'Вы не записаны на это мероприятие');
            }
            Database::exec("UPDATE registrations SET status = 'CANCELLED', updated_at = now() WHERE id = ?", [$reg['id']]);

            if ($reg['status'] === 'ACTIVE') {
                EventService::promoteWaitlist($eventId); // место освободилось — отдаём первому из очереди
            }
        });

        Http::json(['message' => 'Запись отменена']);
    }

    /**
     * GET /api/events/{eventId}/waitlist
     * Всем: размер очереди и своя позиция. Владельцу и админу — ещё и список людей.
     */
    public function waitlist(int $eventId): void
    {
        $user = Auth::requireUser();
        $event = EventService::find($eventId);

        $rows = Database::all(
            "SELECT u.id, u.name, r.updated_at AS since FROM registrations r JOIN users u ON u.id = r.user_id
             WHERE r.event_id = ? AND r.status = 'WAITLIST' ORDER BY r.updated_at, r.id",
            [$eventId]
        );
        $result = [
            'count'      => count($rows),
            'myPosition' => $this->position($eventId, (int)$user['id']),
        ];
        if ($user['role'] === 'ADMIN' || (int)$event['owner_id'] === (int)$user['id']) {
            $result['users'] = array_map(fn($r, $i) => [
                'position' => $i + 1,
                'userId'   => (int)$r['id'],
                'name'     => $r['name'],
                'since'    => $r['since'],
            ], $rows, array_keys($rows));
        }
        Http::json($result);
    }

    /** Номер пользователя в очереди (1, 2, ...) или null */
    private function position(int $eventId, int $userId): ?int
    {
        $rows = Database::all(
            "SELECT user_id FROM registrations WHERE event_id = ? AND status = 'WAITLIST' ORDER BY updated_at, id",
            [$eventId]
        );
        $index = array_search($userId, array_map('intval', array_column($rows, 'user_id')), true);
        return $index === false ? null : $index + 1;
    }
}
