<?php
// Маршруты Backend-1: пользователи, мероприятия, запись / список ожидания, панель админа

use App\Controllers\UserController;
use App\Controllers\EventController;
use App\Controllers\RegistrationController;
use App\Controllers\AdminController;

/** @var App\Core\Router $router */

// Пользователи
$router->post('/api/users', [UserController::class, 'register']);
$router->post('/api/users/auth', [UserController::class, 'login']);
$router->post('/api/users/logout', [UserController::class, 'logout']);
$router->get('/api/users/me', [UserController::class, 'me']);

// Мероприятия
$router->post('/api/events/search', [EventController::class, 'search']);
$router->get('/api/events/my', [EventController::class, 'my']);
$router->post('/api/events', [EventController::class, 'create']);
$router->get('/api/events/{id}', [EventController::class, 'get']);
$router->put('/api/events/{id}', [EventController::class, 'update']);
$router->delete('/api/events/{id}', [EventController::class, 'cancel']);

// Запись на мероприятие и список ожидания
$router->post('/api/events/registrations/{eventId}', [RegistrationController::class, 'register']);
$router->delete('/api/events/registrations/cancel/{eventId}', [RegistrationController::class, 'cancel']);
$router->get('/api/events/{eventId}/waitlist', [RegistrationController::class, 'waitlist']);

// Подтверждение создания / отмены мероприятия администратором
$router->get('/api/admin/requests', [AdminController::class, 'list']);
$router->post('/api/admin/requests/{id}/approve', [AdminController::class, 'approve']);
$router->post('/api/admin/requests/{id}/reject', [AdminController::class, 'reject']);
