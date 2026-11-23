<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Http;
use App\Core\HttpException;

/** Рейтинг и комментарии к мероприятиям */
class ReviewController
{
    /** GET /api/events/{eventId}/reviews — средний рейтинг и все отзывы */
    public function list(int $eventId): void
    {
        if (!Database::one('SELECT id FROM events WHERE id = ?', [$eventId])) {
            throw new HttpException(404, 'Мероприятие не найдено');
        }
        $summary = Database::one(
            'SELECT ROUND(AVG(rating), 1) AS avg, COUNT(*) AS cnt FROM reviews WHERE event_id = ?',
            [$eventId]
        );
        $rows = Database::all(
            'SELECT rv.*, u.name AS author FROM reviews rv JOIN users u ON u.id = rv.user_id
             WHERE rv.event_id = ? ORDER BY rv.created_at DESC',
            [$eventId]
        );
        Http::json([
            'rating' => $summary['avg'] !== null ? (float)$summary['avg'] : null,
            'count'  => (int)$summary['cnt'],
            'items'  => array_map([$this, 'toJson'], $rows),
        ]);
    }

    /** POST /api/events/{eventId}/reviews  body: {"rating": 5, "comment": "..."} */
    public function create(int $eventId): void
    {
        $user = Auth::requireUser();
        $d = Http::body();
        Http::require($d, ['rating', 'comment']);
        $rating = (int)$d['rating'];
        if ($rating < 1 || $rating > 5) {
            throw new HttpException(400, 'Оценка — от 1 до 5');
        }
        if (mb_strlen(trim($d['comment'])) < 3) {
            throw new HttpException(400, 'Комментарий слишком короткий');
        }

        $event = Database::one('SELECT status FROM events WHERE id = ?', [$eventId]);
        if (!$event) {
            throw new HttpException(404, 'Мероприятие не найдено');
        }
        if ($event['status'] !== 'FINISHED') {
            throw new HttpException(409, 'Отзыв можно оставить только после завершения мероприятия');
        }
        $visited = Database::one(
            "SELECT id FROM registrations WHERE event_id = ? AND user_id = ? AND status = 'ACTIVE'",
            [$eventId, $user['id']]
        );
        if (!$visited) {
            throw new HttpException(403, 'Отзыв могут оставить только участники мероприятия');
        }
        if (Database::one('SELECT id FROM reviews WHERE event_id = ? AND user_id = ?', [$eventId, $user['id']])) {
            throw new HttpException(409, 'Вы уже оставили отзыв');
        }

        $row = Database::one(
            'INSERT INTO reviews (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?) RETURNING *',
            [$eventId, $user['id'], $rating, trim($d['comment'])]
        );
        $row['author'] = $user['name'];
        Http::json($this->toJson($row), 201);
    }

    /** DELETE /api/reviews/{id} — автор или админ */
    public function delete(int $id): void
    {
        $user = Auth::requireUser();
        $review = Database::one('SELECT user_id FROM reviews WHERE id = ?', [$id]);
        if (!$review) {
            throw new HttpException(404, 'Отзыв не найден');
        }
        if ($user['role'] !== 'ADMIN' && (int)$review['user_id'] !== (int)$user['id']) {
            throw new HttpException(403, 'Удалить можно только свой отзыв');
        }
        Database::exec('DELETE FROM reviews WHERE id = ?', [$id]);
        Http::json(['message' => 'Отзыв удалён']);
    }



    /** GET /api/reviews/latest?limit=6 — последние отзывы для главной страницы + общий рейтинг */
    public function latest(): void
    {
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 6)));
        $rows = Database::all(
            "SELECT rv.*, u.name AS author, e.name AS event_name FROM reviews rv
             JOIN users u ON u.id = rv.user_id JOIN events e ON e.id = rv.event_id
             ORDER BY rv.created_at DESC LIMIT $limit"
        );
        $avg = Database::one('SELECT ROUND(AVG(rating), 1) AS avg FROM reviews')['avg'];
        Http::json([
            'rating' => $avg !== null ? (float)$avg : null,
            'items'  => array_map([$this, 'toJson'], $rows),
        ]);
    }

    private function toJson(array $r): array
    {
        return [
            'id'        => (int)$r['id'],
            'eventId'   => (int)$r['event_id'],
            'eventName' => $r['event_name'] ?? null,
            'userId'    => (int)$r['user_id'],
            'author'    => $r['author'],
            'rating'    => (int)$r['rating'],
            'comment'   => $r['comment'],
            'createdAt' => (new \DateTime($r['created_at']))->format(DATE_ATOM),
        ];
    }
}
