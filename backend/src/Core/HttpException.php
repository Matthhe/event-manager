<?php
namespace App\Core;

/** Бросаем, когда нужно вернуть клиенту ошибку с кодом (400, 401, 403, 404, 409...) */
class HttpException extends \Exception
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}
