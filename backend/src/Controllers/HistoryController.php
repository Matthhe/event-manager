<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;

/** История записей пользователя */
class HistoryController
{
    /**
     * GET /api/events/registrations/my
     * historyStatus: REGISTERED (записан), WAITLIST (в списке ожидания), ATTENDED (посетил),
     *                CANCELLED (сам отменил запись), EVENT_CANCELLED (мероприятие отменено)
     */
    public function my(): void
    {
        $user = Auth::requireUser();
        $rows = Database::all(
            "SELECT r.status AS reg_status, r.created_at AS registered_at, r.updated_at,
                    e.id AS event_id, e.name, e.date, e.status AS event_status, l.name AS location_name,
                    (SELECT COUNT(*) + 1 FROM registrations w
                       WHERE w.event_id = r.event_id AND w.status = 'WAITLIST'
                         AND (w.updated_at, w.id) < (r.updated_at, r.id)) AS waitlist_position,
                    EXISTS (SELECT 1 FROM reviews rv WHERE rv.event_id = e.id AND rv.user_id = r.user_id) AS has_review
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             JOIN locations l ON l.id = e.location_id
             WHERE r.user_id = ?
             ORDER BY e.date DESC",
            [$user['id']]
        );

        Http::json(array_map(function ($r) {
            $status = $this->historyStatus($r['reg_status'], $r['event_status']);
            return [
                'eventId'          => (int)$r['event_id'],
                'eventName'        => $r['name'],
                'date'             => (new \DateTime($r['date']))->format(DATE_ATOM),
                'locationName'     => $r['location_name'],
                'eventStatus'      => $r['event_status'],
                'registrationStatus' => $r['reg_status'],
                'historyStatus'    => $status,
                'waitlistPosition' => $status === 'WAITLIST' ? (int)$r['waitlist_position'] : null,
                'canReview'        => $status === 'ATTENDED' && !$r['has_review'],
                'registeredAt'     => (new \DateTime($r['registered_at']))->format(DATE_ATOM),
            ];
        }, $rows));
    }

    private function historyStatus(string $reg, string $event): string
    {
        if ($event === 'CANCELLED') {
            return 'EVENT_CANCELLED';
        }
        return match ($reg) {
            'CANCELLED' => 'CANCELLED',
            'WAITLIST'  => 'WAITLIST',
            default     => $event === 'FINISHED' ? 'ATTENDED' : 'REGISTERED',
        };
    }
}
