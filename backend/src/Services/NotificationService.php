<?php
namespace App\Services;

use App\Core\Database;
use App\Core\RedisClient;

/** Создание уведомлений. Вызывается из бизнес-логики (изменение, отмена, старт мероприятия и т.д.) */
class NotificationService
{
    /** Ключ Redis со счётчиком непрочитанных уведомлений пользователя */
    public static function unreadKey(int $userId): string
    {
        return "notif:unread:$userId";
    }

    /**
     * @param int[]  $userIds кому отправить
     * @param array  $changes список изменений [['field' => 'date', 'oldValue' => ..., 'newValue' => ...]]
     */
    public static function notify(array $userIds, ?int $eventId, string $type, string $message, array $changes = []): void
    {
        $userIds = array_unique(array_map('intval', $userIds));
        if (!$userIds) {
            return;
        }
        $redis = RedisClient::get();
        $json = json_encode($changes, JSON_UNESCAPED_UNICODE);
        foreach ($userIds as $userId) {
            Database::exec(
                'INSERT INTO notifications (user_id, event_id, type, message, changes) VALUES (?, ?, ?, ?, ?::jsonb)',
                [$userId, $eventId, $type, $message, $json]
            );
            // счётчик увеличиваем, только если он уже есть в кэше; иначе его заново посчитает GET /notifications/unread-count
            if ($redis->exists(self::unreadKey($userId))) {
                $redis->incr(self::unreadKey($userId));
            }
        }
    }

    /** id всех, кто записан или стоит в списке ожидания */
    public static function participants(int $eventId): array
    {
        $rows = Database::all(
            "SELECT user_id FROM registrations WHERE event_id = ? AND status IN ('ACTIVE', 'WAITLIST')",
            [$eventId]
        );
        return array_column($rows, 'user_id');
    }
}
