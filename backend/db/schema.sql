-- Схема БД EventHub (PostgreSQL)

CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    login         VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    age           INT CHECK (age >= 0),
    role          VARCHAR(10)  NOT NULL DEFAULT 'USER' CHECK (role IN ('USER', 'ADMIN')),
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE locations (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    address     VARCHAR(255) NOT NULL,
    capacity    INT NOT NULL CHECK (capacity >= 5),
    description TEXT
);

-- PENDING    — ждёт подтверждения создания админом
-- REJECTED   — админ отклонил создание
-- WAIT_START — подтверждено, ждёт начала
-- STARTED / FINISHED / CANCELLED
CREATE TABLE events (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    owner_id    INT NOT NULL REFERENCES users(id),
    location_id INT NOT NULL REFERENCES locations(id),
    date        TIMESTAMPTZ NOT NULL,
    duration    INT NOT NULL CHECK (duration >= 10),
    cost        NUMERIC(10, 2) NOT NULL DEFAULT 0 CHECK (cost >= 0),
    max_places  INT NOT NULL CHECK (max_places >= 1),
    status      VARCHAR(15) NOT NULL DEFAULT 'PENDING'
                CHECK (status IN ('PENDING', 'REJECTED', 'WAIT_START', 'STARTED', 'FINISHED', 'CANCELLED')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_events_status ON events(status);
CREATE INDEX idx_events_date   ON events(date);

-- Заявки на создание / отмену мероприятия, которые рассматривает админ
CREATE TABLE event_requests (
    id           SERIAL PRIMARY KEY,
    event_id     INT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    type         VARCHAR(10) NOT NULL CHECK (type IN ('CREATE', 'CANCEL')),
    status       VARCHAR(10) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED')),
    requested_by INT NOT NULL REFERENCES users(id),
    reviewed_by  INT REFERENCES users(id),
    comment      TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    reviewed_at  TIMESTAMPTZ
);

-- ACTIVE — записан, WAITLIST — в списке ожидания, CANCELLED — отменил запись
CREATE TABLE registrations (
    id         SERIAL PRIMARY KEY,
    event_id   INT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id    INT NOT NULL REFERENCES users(id),
    status     VARCHAR(10) NOT NULL CHECK (status IN ('ACTIVE', 'WAITLIST', 'CANCELLED')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (event_id, user_id)
);
CREATE INDEX idx_registrations_user ON registrations(user_id);

-- type: EVENT_UPDATED, EVENT_CANCELLED, EVENT_STARTED, EVENT_FINISHED,
--       WAITLIST_PROMOTED, REQUEST_APPROVED, REQUEST_REJECTED
CREATE TABLE notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id),
    event_id   INT REFERENCES events(id) ON DELETE CASCADE,
    type       VARCHAR(30) NOT NULL,
    message    TEXT NOT NULL,
    changes    JSONB NOT NULL DEFAULT '[]',
    is_read    BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);

CREATE TABLE reviews (
    id         SERIAL PRIMARY KEY,
    event_id   INT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id    INT NOT NULL REFERENCES users(id),
    rating     INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (event_id, user_id)
);
