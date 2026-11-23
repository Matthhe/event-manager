<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\RedisClient;
use App\Services\NotificationService;

/** Просмотр уведомлений и отметка «прочитано». Создаёт уведомления NotificationService (Backend-1). */
class NotificationController
{
    /** GET /api/notifications?onlyUnread=1&page=0&size=20 */
    public function list(): void
    {
        $user = Auth::requireUser();
        [$page, $size] = Http::paging($_GET + ['size' => 20]);
        $where = 'user_id = ?';
        if (!empty($_GET['onlyUnread'])) {
            $where .= ' AND is_read = FALSE';
        }
        $offset = $page * $size;
        $rows = Database::all(
            "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC, id DESC LIMIT $size OFFSET $offset",
            [$user['id']]
        );
        Http::json(array_map(fn($r) => [
            'id'        => (int)$r['id'],
            'eventId'   => $r['event_id'] !== null ? (int)$r['event_id'] : null,
            'type'      => $r['type'],
            'message'   => $r['message'],
            'changes'   => json_decode($r['changes'], true),
            'isRead'    => (bool)$r['is_read'],
            'createdAt' => (new \DateTime($r['created_at']))->format(DATE_ATOM),
        ], $rows));
    }

    /** GET /api/notifications/unread-count — берём из Redis, если нет — считаем в БД и кладём в Redis */
    public function unreadCount(): void
    {
        $user = Auth::requireUser();
        $redis = RedisClient::get();
        $key = NotificationService::unreadKey((int)$user['id']);
        $count = $redis->get($key);
        if ($count === false) {
            $count = $this->countFromDb((int)$user['id']);
            $redis->setex($key, 3600, (string)$count);
        }
        Http::json(['count' => (int)$count]);
    }

    /** POST /api/notifications/read  body: {"notificationIds": [1, 2]} — без списка отмечаются все */
    public function markRead(): void
    {
        $user = Auth::requireUser();
        $ids = Http::body()['notificationIds'] ?? null;

        if (is_array($ids) && $ids) {
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $updated = Database::exec(
                "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND id IN ($placeholders)",
                array_merge([$user['id']], $ids)
            );
        } else {
            $updated = Database::exec('UPDATE notifications SET is_read = TRUE WHERE user_id = ?', [$user['id']]);
        }

        $count = $this->countFromDb((int)$user['id']);
        RedisClient::get()->setex(NotificationService::unreadKey((int)$user['id']), 3600, (string)$count);
        Http::json(['updated' => $updated, 'unread' => $count]);
    }

    private function countFromDb(int $userId): int
    {
        return (int)Database::one(
            'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = FALSE',
            [$userId]
        )['c'];
    }
}
