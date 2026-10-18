# Участник 1 — шапка, поиск, подвал

## Что делал

| Что | Где | Что именно написано |
|---|---|---|
| **HTML** | `index.html`, блоки «УЧАСТНИК 1» | `<header class="header">` (логотип, меню, кнопки входа/регистрации), `<section class="search">` (форма поиска с фильтрами из ТЗ: `name`, `dateStartAfter`, `costMax`, `locationId`, `status`, `placeMin`, `durationMin`), `<footer class="footer">` |
| **CSS Grid (ПК)** | `css/header-search-footer.css` | `.header` — 3 колонки `200px 1fr auto`; `.search__form` — `repeat(4, 1fr)`; `.footer` — `repeat(3, 1fr)`; `.search__submit` — `grid-column: 1 / -1` |
| **Grid (планшет)** | то же, `@media (min-width:768px) and (max-width:1023px)` | форма поиска в 2 колонки, шапка в 2 колонки, `.auth` на всю строку |
| **FlexBox (телефон)** | то же, `@media (max-width:767px)` | `.header`, `.search__form`, `.footer` → `display:flex; flex-direction:column`; меню `flex-wrap:wrap`; кнопки входа `flex:1` |
| **Общий каркас** | `css/layout.css` | переменные `:root`, сброс, `.page { display:grid; grid-template-columns: 2fr 1fr; gap }`, `order` для header / search / footer на телефоне |

## Связь с ТЗ по бэку
Форма поиска = `POST /events/search` (все фильтры необязательны). Кнопки «Войти» и «Регистрация» = `POST /users/auth` и `POST /users`. Счётчик в меню — число непрочитанных уведомлений (`GET /notifications`).

## Что уметь менять на защите

- **Сделать поиск в 3 колонки** → `.search__form { grid-template-columns: repeat(3, 1fr); }`
- **Колонки разной ширины** → `grid-template-columns: 2fr 1fr 1fr 1fr;`
- **Поле «Название» на всю строку** → `.field:first-child { grid-column: 1 / -1; }`
- **Колонки автоматически по ширине** → `repeat(auto-fit, minmax(200px, 1fr))`
- **Отступы между полями** → `gap: 20px;` (или `row-gap` / `column-gap` отдельно)
- **На телефоне меню в столбик** → `.nav__list { flex-direction: column; }`
- **Расположить элементы шапки по краям (flex)** → `justify-content: space-between;`
- **Выровнять по центру по вертикали** → `align-items: center;`
- **Сменить порог телефона** → поменять `767px` на `600px` (и `768px` на `601px` в планшетном запросе)

## Принципы, которые надо объяснить
- **Grid** — двумерная раскладка (строки и колонки сразу). `fr` — доля свободного места, `gap` — промежуток, `grid-column: 1 / -1` — растянуть от первой до последней линии.
- **Flexbox** — одномерная раскладка (один ряд или одна колонка). `flex-direction`, `justify-content` (по главной оси), `align-items` (по поперечной), `flex-wrap`, `flex: 1`.
- **Media-запрос** `@media (max-width: 767px)` — правила действуют, только если экран не шире 767px. На телефоне мы выключаем Grid (`display:flex`) и строим всё в столбик.
