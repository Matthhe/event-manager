-- Тестовые данные EventHub
-- Пользователи: admin / admin123 (ADMIN); anna, boris, maria / password (USER)

INSERT INTO users (login, password_hash, name, age, role) VALUES
('admin', '$2y$10$oq57rF3q6uEq2vyJUw9uIu5YAvbsUqWB9m4ruLUQWu0c1xW.M1KLy', 'Администратор', 30, 'ADMIN'),
('anna',  '$2y$10$RRau2cdjc8ieDiV4l2p.kOhTBhJDJkAk9fzDLr6EVzy9YX0zfaGkS', 'Анна',  21, 'USER'),
('boris', '$2y$10$RRau2cdjc8ieDiV4l2p.kOhTBhJDJkAk9fzDLr6EVzy9YX0zfaGkS', 'Борис', 22, 'USER'),
('maria', '$2y$10$RRau2cdjc8ieDiV4l2p.kOhTBhJDJkAk9fzDLr6EVzy9YX0zfaGkS', 'Мария', 20, 'USER');

INSERT INTO locations (name, address, capacity, description) VALUES
('Конференц-зал',     'ул. Ленина, 1',    100, 'Проектор, звук, Wi-Fi.'),
('Коворкинг «Центр»', 'пр. Мира, 15',     40,  'Столы, флипчарты.'),
('Актовый зал',       'ул. Школьная, 7',  150, 'Сцена, кресла.');

-- Даты считаются от текущего момента, чтобы данные всегда были «живыми»
INSERT INTO events (name, description, owner_id, location_id, date, duration, cost, max_places, status) VALUES
('Митап по Java', 'Доклады о Spring Boot и практика. Подходит для начинающих.',
    2, 1, now() + INTERVAL '7 days', 120, 0, 50, 'WAIT_START'),
('Воркшоп по CSS Grid', 'Практическая вёрстка. Мест мало — можно встать в список ожидания.',
    3, 2, now() + INTERVAL '10 days', 90, 10, 2, 'WAIT_START'),
('Вечер настольных игр', 'Неформальная встреча, игры и общение.',
    2, 3, now() + INTERVAL '12 days', 180, 5, 30, 'WAIT_START'),
('Конференция по Docker', 'Контейнеры в продакшене.',
    3, 1, now() - INTERVAL '30 days', 240, 0, 60, 'FINISHED'),
('Мастер-класс по Git', 'Ветки, rebase, конфликты.',
    2, 2, now() + INTERVAL '20 days', 120, 0, 15, 'PENDING'),
('Хакатон', 'Соревнование команд за 10 часов.',
    3, 1, now() + INTERVAL '25 days', 600, 0, 40, 'WAIT_START');

INSERT INTO event_requests (event_id, type, status, requested_by, reviewed_by, reviewed_at) VALUES
(1, 'CREATE', 'APPROVED', 2, 1, now()),
(2, 'CREATE', 'APPROVED', 3, 1, now()),
(3, 'CREATE', 'APPROVED', 2, 1, now()),
(4, 'CREATE', 'APPROVED', 3, 1, now() - INTERVAL '40 days'),
(6, 'CREATE', 'APPROVED', 3, 1, now());
INSERT INTO event_requests (event_id, type, requested_by) VALUES
(5, 'CREATE', 2),   -- ждёт подтверждения создания
(6, 'CANCEL', 3);   -- владелец хочет отменить хакатон

INSERT INTO registrations (event_id, user_id, status, created_at, updated_at) VALUES
(1, 3, 'ACTIVE',   now(), now()),
(2, 2, 'ACTIVE',   now() - INTERVAL '3 hours', now() - INTERVAL '3 hours'),
(2, 4, 'ACTIVE',   now() - INTERVAL '2 hours', now() - INTERVAL '2 hours'),
(3, 4, 'ACTIVE',   now(), now()),
(4, 2, 'ACTIVE',   now() - INTERVAL '35 days', now() - INTERVAL '35 days'),
(4, 3, 'ACTIVE',   now() - INTERVAL '35 days', now() - INTERVAL '35 days'),
(4, 4, 'ACTIVE',   now() - INTERVAL '35 days', now() - INTERVAL '35 days');

INSERT INTO reviews (event_id, user_id, rating, comment) VALUES
(4, 2, 5, 'Отличная конференция, много практики!'),
(4, 3, 4, 'Интересно, но было тесновато.');

INSERT INTO notifications (user_id, event_id, type, message, changes) VALUES
(3, 1, 'EVENT_UPDATED', 'Мероприятие «Митап по Java» изменено',
    '[{"field": "duration", "oldValue": "90", "newValue": "120"}]'),
(4, 4, 'EVENT_FINISHED', 'Мероприятие «Конференция по Docker» завершилось. Оставьте отзыв!', '[]');
