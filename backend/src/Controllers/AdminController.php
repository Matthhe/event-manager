<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;
use App\Services\EventService;
use App\Services\NotificationService;

/** Панель администратора: подтверждение создания и отмены мероприятий */
class AdminController
{
    /** GET /api/admin/requests?status=PENDING */
    public function list(): void
    {
        Auth::requireAdmin();
        $status = $_GET['status'] ?? 'PENDING';
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            throw new HttpException(400, 'status: PENDING, APPROVED или REJECTED');
        }
        $rows = Database::all(
            "SELECT er.*, e.name AS event_name, e.date AS event_date, e.status AS event_status,
                    u.name AS requested_by_name
             FROM event_requests er
             JOIN events e ON e.id = er.event_id
             JOIN users u ON u.id = er.requested_by
             WHERE er.status = ? ORDER BY er.created_at",
            [$status]
        );
        Http::json(array_map(fn($r) => [
            'id'              => (int)$r['id'],
            'type'            => $r['type'],
            'status'          => $r['status'],
            'eventId'         => (int)$r['event_id'],
            'eventName'       => $r['event_name'],
            'eventDate'       => (new \DateTime($r['event_date']))->format(DATE_ATOM),
            'eventStatus'     => $r['event_status'],
            'requestedBy'     => $r['requested_by_name'],
            'comment'         => $r['comment'],
            'createdAt'       => (new \DateTime($r['created_at']))->format(DATE_ATOM),
        ], $rows));
    }

    /** POST /api/admin/requests/{id}/approve */
    public function approve(int $id): void
    {
        $admin = Auth::requireAdmin();
        EventService::transaction(function () use ($id, $admin) {
            $req = $this->lockPending($id);
            $event = EventService::lock((int)$req['event_id']);

            if ($req['type'] === 'CREATE') {
                Database::exec("UPDATE events SET status = 'WAIT_START', updated_at = now() WHERE id = ?", [$event['id']]);
                $message = "Создание мероприятия «{$event['name']}» подтверждено";
            } else {
                if ($event['status'] !== 'WAIT_START') {
                    throw new HttpException(409, 'Мероприятие уже началось или отменено');
                }
                EventService::cancelEvent((int)$event['id']);
                $message = "Отмена мероприятия «{$event['name']}» подтверждена";
            }
            $this->close($id, 'APPROVED', (int)$admin['id'], null);
            NotificationService::notify([$req['requested_by']], (int)$event['id'], 'REQUEST_APPROVED', $message);
        });
        Http::json(['message' => 'Заявка подтверждена']);
    }

    /** POST /api/admin/requests/{id}/reject  body: {"comment": "причина"} */
    public function reject(int $id): void
    {
        $admin = Auth::requireAdmin();
        $comment = Http::body()['comment'] ?? null;
        EventService::transaction(function () use ($id, $admin, $comment) {
            $req = $this->lockPending($id);
            $event = EventService::lock((int)$req['event_id']);

            if ($req['type'] === 'CREATE') {
                Database::exec("UPDATE events SET status = 'REJECTED', updated_at = now() WHERE id = ?", [$event['id']]);
                $message = "Создание мероприятия «{$event['name']}» отклонено";
            } else {
                $message = "Отмена мероприятия «{$event['name']}» отклонена — мероприятие состоится";
            }
            if ($comment) {
                $message .= ". Причина: $comment";
            }
            $this->close($id, 'REJECTED', (int)$admin['id'], $comment);
            NotificationService::notify([$req['requested_by']], (int)$event['id'], 'REQUEST_REJECTED', $message);
        });
        Http::json(['message' => 'Заявка отклонена']);
    }

    private function lockPending(int $id): array
    {
        $req = Database::one('SELECT * FROM event_requests WHERE id = ? FOR UPDATE', [$id]);
        if (!$req) {
            throw new HttpException(404, 'Заявка не найдена');
        }
        if ($req['status'] !== 'PENDING') {
            throw new HttpException(409, 'Заявка уже рассмотрена');
        }
        return $req;
    }

    private function close(int $id, string $status, int $adminId, ?string $comment): void
    {
        Database::exec(
            'UPDATE event_requests SET status = ?, reviewed_by = ?, comment = ?, reviewed_at = now() WHERE id = ?',
            [$status, $adminId, $comment, $id]
        );
    }
}
