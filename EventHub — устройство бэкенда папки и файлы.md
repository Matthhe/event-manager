# EventHub — устройство бэкенда: папки и файлы

Nov 24, 2026 · @Mateusz

## Структура

Бэкенд — PHP-приложение без фреймворка из пяти слоёв: точка входа, маршруты, контроллеры, сервисы и ядро. Все запросы идут через один файл `public/index.php`, а данные хранятся в PostgreSQL и Redis.

```
backend/
├── Dockerfile
├── public/          ← единственная папка, доступная из интернета
│   ├── .htaccess
│   └── index.php
├── routes/          ← какие URL существуют и кто их обрабатывает
│   ├── main.php
│   └── extra.php
├── src/             ← весь код приложения
│   ├── Core/        ← инфраструктура: БД, Redis, HTTP, роутинг, авторизация
│   ├── Services/    ← общая бизнес-логика для нескольких контроллеров
│   └── Controllers/ ← обработчики эндпоинтов
├── db/              ← SQL для создания базы
│   ├── schema.sql
│   └── seed.sql
└── bin/             ← скрипты для запуска из консоли, не из браузера
    └── update-statuses.php
```

**Как слои связаны.** Каждый слой обращается только к тем, что ниже; например, `Core/` ничего не знает о контроллерах.

1. `public/index.php` — принимает запрос
2. `routes/` — определяет, какой контроллер его обрабатывает
3. `Controllers/` — проверяет права и данные, выполняет действие
4. `Services/` — общая логика, которую вызывают контроллеры
5. `Core/` — работа с PostgreSQL, Redis и HTTP

**`Dockerfile`** — инструкция сборки контейнера с бэкендом: образ PHP 8.3 с Apache, расширения `pdo_pgsql` (PostgreSQL) и `redis`, включённый `mod_rewrite`, корень сайта — папка `public/`. В корне проекта лежит `docker-compose.yml`: он поднимает 4 контейнера — `db` (PostgreSQL), `redis`, `api` (этот бэкенд) и `scheduler` (смена статусов раз в минуту).

## public/ и routes/ — точка входа и маршруты

**`public/`** — единственная папка, которую Apache отдаёт наружу. Код из `src/`, SQL и скрипты по URL открыть нельзя.

| Файл | За что отвечает |
| --- | --- |
| `.htaccess` | Настройки Apache: любой URL (`/api/events/5`) перенаправляется в `index.php`; заголовок `Authorization` пробрасывается в PHP |
| `index.php` | Принимает каждый запрос: автозагрузка классов (`App\Controllers\X` → `src/Controllers/X.php`), CORS-заголовки (чтобы фронт с порта 5173 мог обращаться к API), подключение маршрутов, запуск роутера. Ловит `HttpException` и отдаёт `{"error": "..."}` с его кодом; любая другая ошибка → 500 |

**`routes/`** — таблица маршрутов без логики: только соответствие «метод + URL → контроллер и метод». Например, `$router->post('/api/users', [UserController::class, 'register'])`.

| Файл | Какие маршруты |
| --- | --- |
| `main.php` | Пользователи, мероприятия, запись и список ожидания, панель админа — 16 маршрутов |
| `extra.php` | Площадки, отзывы, уведомления, история — 13 маршрутов |

Файла два, чтобы маршруты двух частей бэкенда лежали отдельно и не смешивались в git.

## src/Core/ — инфраструктура

Ядро ничего не знает о мероприятиях: это «движок», который можно перенести в любой другой проект.

| Файл | За что отвечает |
| --- | --- |
| `Database.php` | Подключение к PostgreSQL (одно на запрос). Методы: `all()` — все строки, `one()` — одна строка или `null`, `exec()` — INSERT/UPDATE/DELETE, возвращает число затронутых строк. Все запросы с параметрами `?`, поэтому SQL-инъекции исключены |
| `RedisClient.php` | Подключение к Redis. В комментарии перечислены все ключи проекта |
| `Router.php` | Регистрирует маршруты, превращает `{id}` в регулярное выражение (только цифры), находит обработчик и вызывает его. Нет такого URL → 404, URL есть, но не тот метод → 405 |
| `Http.php` | Помощники для HTTP: `body()` читает JSON из запроса, `json()` отправляет ответ (код, заголовок `Content-Type`, тело через `echo`), `require()` проверяет обязательные поля, `paging()` разбирает номер и размер страницы |
| `HttpException.php` | Исключение с HTTP-кодом: `throw new HttpException(409, '...')` — и клиент получает ошибку 409 с этим текстом |
| `Auth.php` | Авторизация: выдаёт токен при входе и кладёт его в Redis (`auth:token:{токен}` → id, 24 ч), по токену из заголовка находит пользователя, удаляет токен при выходе. `requireUser()` → 401 без входа, `requireAdmin()` → 403 не админу |

## src/Services/ — общая бизнес-логика

Код, который нужен в нескольких контроллерах, вынесен сюда, чтобы не дублировать его.

| Файл | За что отвечает | Кто использует |
| --- | --- | --- |
| `EventService.php` | Общий SQL-запрос мероприятия со всеми подсчётами (занято мест, размер очереди, рейтинг, статус записи текущего пользователя); перевод в JSON; блокировка строки для транзакции (`SELECT … FOR UPDATE`); отмена мероприятия; продвижение списка ожидания; обёртка для транзакций | Event-, Registration-, AdminController |
| `NotificationService.php` | Создание уведомлений в БД и увеличение счётчика непрочитанных в Redis (`notif:unread:{id}`); список участников мероприятия | Event-, Admin-, NotificationController, EventService, EventStatusUpdater |
| `EventStatusUpdater.php` | Смена статусов по времени: `WAIT_START → STARTED → FINISHED`, неподтверждённые к началу `PENDING → REJECTED`. Рассылает уведомления о начале и завершении | `bin/update-statuses.php` |

## src/Controllers/ — эндпоинты

Каждый контроллер отвечает за одну область, каждый метод — за один эндпоинт. Схема метода везде одна: проверить права (`Auth`), проверить данные, выполнить действие, отдать JSON (`Http::json`).

| Файл | Чей | Методы |
| --- | --- | --- |
| `UserController.php` | Backend-1 | `register` — регистрация; `login` — вход с защитой от подбора пароля (`auth:fail:{логин}` в Redis); `logout`; `me` — текущий пользователь |
| `EventController.php` | Backend-1 | `create`, `get`, `update` (с уведомлением участников), `cancel` (админ сразу, владелец через заявку), `search` (фильтры и страницы), `my` (созданные мной) |
| `RegistrationController.php` | Backend-1 | `register` — записаться или встать в очередь; `cancel` — отменить запись и продвинуть очередь; `waitlist` — список ожидания |
| `AdminController.php` | Backend-1 | `list` — заявки; `approve`, `reject` — решение по заявке на создание или отмену |
| `LocationController.php` | Backend-2 | `list` (с кэшем в Redis), `get`, `create`, `update`, `delete` |
| `ReviewController.php` | Backend-2 | `list` — отзывы мероприятия и рейтинг; `create`; `delete`; `latest` — последние отзывы для главной |
| `NotificationController.php` | Backend-2 | `list`, `unreadCount` (из Redis), `markRead` |
| `HistoryController.php` | Backend-2 | `my` — история записей с итоговым статусом |

## db/ и bin/ — база данных и консольные скрипты

**`db/`** — оба файла выполняются автоматически только при первом создании базы: `docker-compose.yml` монтирует их в папку инициализации PostgreSQL. Чтобы применить их заново: `docker compose down -v && docker compose up -d`.

| Файл | За что отвечает |
| --- | --- |
| `schema.sql` | Структура: 7 таблиц (`users`, `locations`, `events`, `event_requests`, `registrations`, `notifications`, `reviews`), внешние ключи, ограничения `CHECK` и `UNIQUE`, индексы |
| `seed.sql` | Тестовые данные: 4 пользователя (`admin / admin123`, `anna`, `boris`, `maria` / `password`), 3 площадки, 6 мероприятий, заявки, записи, отзывы, уведомления |

**`bin/`** — скрипты, которые запускаются из консоли, а не через браузер.

| Файл | За что отвечает |
| --- | --- |
| `update-statuses.php` | Запускает `EventStatusUpdater::run()` и печатает результат вида `{"started":0,"finished":1,"expired":0}`. Его раз в минуту вызывает контейнер `scheduler` (логи: `docker compose logs scheduler`). Вручную: `docker compose exec api php bin/update-statuses.php` |

## Redis — для чего и где используется

Redis — вторая база данных, «быстрая и временная». В PostgreSQL лежит всё, что нельзя потерять (пользователи, мероприятия, записи, отзывы), а в Redis — данные, которые нужны часто и быстро, живут ограниченное время и при потере восстанавливаются без вреда. В проекте он решает четыре задачи.

| Задача | Ключ → значение | Время жизни | Файлы |
| --- | --- | --- | --- |
| Токены входа | `auth:token:{токен}` → id пользователя | 24 ч | `Core/Auth.php` |
| Защита от подбора пароля | `auth:fail:{логин}` → число попыток | 15 мин | `Controllers/UserController.php` |
| Счётчик непрочитанных уведомлений | `notif:unread:{userId}` → число | 1 ч | `Services/NotificationService.php`, `Controllers/NotificationController.php` |
| Кэш списка площадок | `cache:locations` → JSON со всеми площадками | 10 мин | `Controllers/LocationController.php` |

### 1. Токены входа — `src/Core/Auth.php`

```php
// стр. 16 — при входе: сохранить токен на 24 часа
RedisClient::get()->setex("auth:token:$token", self::TOKEN_TTL, (string)$userId);

// стр. 50 — при каждом запросе: по токену узнать, кто это
$userId = RedisClient::get()->get("auth:token:$token");

// стр. 24 — при выходе: удалить токен
RedisClient::get()->del("auth:token:$token");
```

Зачем Redis: токен сам исчезает через 24 часа (TTL), не нужны ни таблица сессий, ни её чистка. Проверка идёт в памяти при каждом запросе и не нагружает основную базу. По найденному id пользователь берётся из PostgreSQL, чтобы роль всегда была актуальной.

### 2. Защита от подбора пароля — `src/Controllers/UserController.php`, метод `login`

```php
$redis = RedisClient::get();                                   // стр. 45
$failKey = 'auth:fail:' . $data['login'];
if ((int)$redis->get($failKey) >= self::MAX_FAILS) {           // 5 ошибок → 429
    throw new HttpException(429, 'Слишком много попыток входа...');
}
...
$redis->incr($failKey);                                        // стр. 53 — неверный пароль: +1
$redis->expire($failKey, self::BLOCK_SECONDS);                 //          счётчик живёт 15 минут
...
$redis->del($failKey);                                         // стр. 58 — успешный вход: сбросить
```

Зачем Redis: `INCR` атомарно увеличивает счётчик, а через 15 минут блокировка снимается сама.

### 3. Счётчик непрочитанных уведомлений — `NotificationService.php` и `NotificationController.php`

```php
// NotificationService, стр. 34–35 — пришло новое уведомление: +1 (если счётчик уже в кэше)
if ($redis->exists(self::unreadKey($userId))) {
    $redis->incr(self::unreadKey($userId));
}

// NotificationController::unreadCount, стр. 42–47 — отдать число; если ключа нет, посчитать в БД и сохранить
$count = $redis->get($key);
if ($count === false) {
    $count = $this->countFromDb((int)$user['id']);
    $redis->setex($key, 3600, (string)$count);
}

// NotificationController::markRead, стр. 70 — после «прочитано» пересчитать из БД
RedisClient::get()->setex(NotificationService::unreadKey((int)$user['id']), 3600, (string)$count);
```

Зачем Redis: фронт запрашивает счётчик для шапки сайта постоянно, и без кэша это был бы `COUNT(*)` по таблице уведомлений на каждой странице.

### 4. Кэш списка площадок — `src/Controllers/LocationController.php`

```php
// list(), стр. 19–27 — есть в кэше → отдать оттуда (X-Cache: HIT), нет → взять из БД и положить в кэш (MISS)
$cached = $redis->get(self::CACHE_KEY);
if ($cached !== false) { ... return; }
$rows = ... Database::all('SELECT * FROM locations ORDER BY name') ...;
$redis->setex(self::CACHE_KEY, self::CACHE_TTL, json_encode($rows, JSON_UNESCAPED_UNICODE));

// create() стр. 62, update() стр. 87, delete() стр. 101 — площадки изменились → сбросить кэш
RedisClient::get()->del(self::CACHE_KEY);
```

Зачем Redis: площадки меняются редко, а список нужен на каждой загрузке главной страницы и формы мероприятия.

### Где Redis подключается

| Файл | Что делает |
| --- | --- |
| `docker-compose.yml` | сервис `redis` (образ `redis:7`, порт 6379); у `api` переменная `REDIS_HOST: redis` |
| `backend/Dockerfile` | `pecl install redis` — PHP-расширение для работы с Redis |
| `src/Core/RedisClient.php` | одно подключение на запрос; остальные файлы получают его через `RedisClient::get()` |

**Посмотреть вживую:** `docker compose exec redis redis-cli`, затем `KEYS *`. После входа на сайт и открытия главной появятся все четыре вида ключей; `TTL <ключ>` покажет, сколько секунд ключу осталось жить.

**Коротко для защиты:** «Redis хранит временные и часто запрашиваемые данные — сессии, счётчики, кэш. У них есть срок жизни, они работают в памяти и не нагружают PostgreSQL. При потере всё восстанавливается: пользователь заново войдёт, а кэш и счётчики пересчитаются из основной базы».
