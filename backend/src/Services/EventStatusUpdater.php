<?php
namespace App\Services;

use App\Core\Database;

/**
 * Автоматическая смена статусов по времени (запускается раз в минуту из bin/update-statuses.php):
 *   WAIT_START -> STARTED  когда наступила дата начала
 *   STARTED    -> FINISHED когда прошла длительность
 *   PENDING    -> REJECTED если админ не успел подтвердить до начала
 */
class EventStatusUpdater
{
    public static function run(): array
    {
        $started = Database::all(
            "UPDATE events SET status = 'STARTED', updated_at = now()
             WHERE status = 'WAIT_START' AND date <= now() RETURNING id, name"
        );
        foreach ($started as $e) {
            NotificationService::notify(
                self::activeUsers((int)$e['id']), (int)$e['id'], 'EVENT_STARTED', "Мероприятие «{$e['name']}» началось"
            );
        }

        $finished = Database::all(
            "UPDATE events SET status = 'FINISHED', updated_at = now()
             WHERE status = 'STARTED' AND date + duration * INTERVAL '1 minute' <= now() RETURNING id, name"
        );
        foreach ($finished as $e) {
            NotificationService::notify(
                self::activeUsers((int)$e['id']), (int)$e['id'], 'EVENT_FINISHED',
                "Мероприятие «{$e['name']}» завершилось. Оставьте отзыв!"
            );
        }

        $expired = Database::all(
            "UPDATE events SET status = 'REJECTED', updated_at = now()
             WHERE status = 'PENDING' AND date <= now() RETURNING id"
        );
        foreach ($expired as $e) {
            Database::exec(
                "UPDATE event_requests SET status = 'REJECTED', comment = 'Не подтверждено до начала', reviewed_at = now()
                 WHERE event_id = ? AND status = 'PENDING'",
                [$e['id']]
            );
        }

        return ['started' => count($started), 'finished' => count($finished), 'expired' => count($expired)];
    }

    private static function activeUsers(int $eventId): array
    {
        return array_column(
            Database::all("SELECT user_id FROM registrations WHERE event_id = ? AND status = 'ACTIVE'", [$eventId]),
            'user_id'
        );
    }
}
