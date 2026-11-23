<?php
namespace App\Core;

/** Помощники для чтения запроса, проверки полей и отправки JSON */
class Http
{
    /** Тело запроса (JSON) в виде массива */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Тело запроса должно быть JSON-объектом');
        }
        return $data;
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** Проверить, что обязательные поля есть и не пустые */
    public static function require(array $data, array $fields): void
    {
        $missing = [];
        foreach ($fields as $f) {
            if (!isset($data[$f]) || $data[$f] === '') {
                $missing[] = $f;
            }
        }
        if ($missing) {
            throw new HttpException(400, 'Не заполнены поля: ' . implode(', ', $missing));
        }
    }

    /** Номер страницы и размер страницы из query (?page=0&size=10) */
    public static function paging(array $src): array
    {
        $page = max(0, (int)($src['page'] ?? 0));
        $size = min(100, max(1, (int)($src['size'] ?? 10)));
        return [$page, $size];
    }
}
