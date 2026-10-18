# Участник 3 — боковая панель (уведомления, история), панель администратора

## Что делал (всё поровну: HTML + Grid + Flexbox + media)

| Что | Где | Что именно написано |
|---|---|---|
| **HTML** | `index.html`, блоки «УЧАСТНИК 3» | `<aside class="sidebar">`: `<section class="notifications">` (уведомления с изменениями «поле: старое → новое», кнопка «Отметить прочитанными») и `<section class="history">` (история записей: записан / в списке ожидания / посетил / отменено). `<section class="admin">` — заявки на создание и отмену мероприятий с кнопками «Подтвердить» / «Отклонить» |
| **CSS Grid (ПК)** | `css/sidebar-admin.css` | `.sidebar` — `grid-template-rows: auto auto`; `.notif` и `.history__item` — `1fr auto`; `.admin__item` — `1fr auto`; `.admin__buttons` — `grid-auto-flow: column`; `grid-area` для `sidebar` и `admin` |
| **Grid (планшет)** | то же, `@media (min-width:768px) and (max-width:1023px)` | `.sidebar` и `.admin` стоят рядом (две колонки по `grid-template-areas`), внутри админ-элементов одна колонка |
| **FlexBox (телефон)** | то же, `@media (max-width:767px)` | `.sidebar` → `flex-direction:column`; `.notif`, `.history__item` → `flex-wrap:wrap; justify-content:space-between`; `.admin__item` → столбик, кнопки `flex:1` |
| **Общий каркас** | `css/layout.css` | `grid-template-areas` для `sidebar` и `admin` (ПК и планшет), `order` на телефоне; цвета и бейджи статусов |

## Связь с ТЗ по бэку и заданием преподавателя
- Уведомления = `GET /notifications` (тип `EVENT_UPDATED / EVENT_CANCELLED / EVENT_STARTED / EVENT_FINISHED`, список `changes`: поле, старое, новое значение). «Отметить прочитанными» = `POST /notifications` с `notificationIds`.
- Уведомление «освободилось место» — для списка ожидания (дополнение преподавателя).
- История = `GET /events/registrations/my` + статусы записей.
- Панель администратора — подтверждение создания / отмены мероприятия (дополнение преподавателя; роль ADMIN).

## Что уметь менять на защите

- **Sidebar уже / шире** → в `layout.css`: `grid-template-columns: 3fr 1fr;` или `1fr 1fr;`
- **Поставить sidebar слева** → в `grid-template-areas` поменять местами `events` и `sidebar`
- **Убрать `sidebar` вниз на ПК** → отдельной строкой в `grid-template-areas`
- **Кнопки админа друг под другом** → `.admin__buttons { grid-auto-flow: row; }`
- **Уведомление: бейдж слева** → `.notif { grid-template-columns: auto 1fr; }`
- **Растянуть заметку об изменении на всю ширину** → `grid-column: 1 / -1;` (уже сделано в `.notif__change`)
- **На телефоне кнопки админа в столбик** → `.admin__buttons { flex-direction: column; }`
- **На телефоне элементы по краям** → `justify-content: space-between;`
- **Поменять порядок блоков на телефоне** → `order` в `layout.css` (например, `.admin { order: 0 }` — вверх)

## Принципы, которые надо объяснить
- **Grid**: `grid-template-areas` называет участки страницы; на планшете областей столько же, но расположены иначе — только изменением шаблона, HTML не трогаем.
- **Flexbox**: главная ось (`flex-direction`) и поперечная; `justify-content` работает по главной, `align-items` — по поперечной.
- **Почему на телефоне Flex**: одна колонка блоков удобнее, а `order` позволяет менять порядок без правки HTML.
