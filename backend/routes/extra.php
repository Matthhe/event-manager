<?php
// Маршруты Backend-2: площадки, рейтинг/комментарии, уведомления, история

use App\Controllers\LocationController;
use App\Controllers\ReviewController;
use App\Controllers\NotificationController;
use App\Controllers\HistoryController;

/** @var App\Core\Router $router */

// Площадки (локации)
$router->get('/api/locations', [LocationController::class, 'list']);
$router->get('/api/locations/{id}', [LocationController::class, 'get']);
$router->post('/api/locations', [LocationController::class, 'create']);
$router->put('/api/locations/{id}', [LocationController::class, 'update']);
$router->delete('/api/locations/{id}', [LocationController::class, 'delete']);

// Рейтинг и комментарии
$router->get('/api/reviews/latest', [ReviewController::class, 'latest']);
$router->get('/api/events/{eventId}/reviews', [ReviewController::class, 'list']);
$router->post('/api/events/{eventId}/reviews', [ReviewController::class, 'create']);
$router->delete('/api/reviews/{id}', [ReviewController::class, 'delete']);

// Уведомления
$router->get('/api/notifications', [NotificationController::class, 'list']);
$router->get('/api/notifications/unread-count', [NotificationController::class, 'unreadCount']);
$router->post('/api/notifications/read', [NotificationController::class, 'markRead']);

// История записей пользователя
$router->get('/api/events/registrations/my', [HistoryController::class, 'my']);
