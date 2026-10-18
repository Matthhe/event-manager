# Участник 2 — мероприятия, площадки, рейтинг и комментарии

## Что делал (всё поровну: HTML + Grid + Flexbox + media)

| Что | Где | Что именно написано |
|---|---|---|
| **HTML** | `index.html`, блоки «УЧАСТНИК 2» | `<section class="events">` — 6 карточек мероприятий в разных состояниях (можно записаться / мест нет → список ожидания / вы записаны → отмена записи / идёт / отменено / ждёт подтверждения админа) с названием, описанием, датой, площадкой, ценой, местами; пагинация. `<section class="locations">` — 3 площадки. `<section class="reviews">` — рейтинг и комментарии |
| **CSS Grid (ПК)** | `css/events-locations-reviews.css` | `.events__grid` — `repeat(2, 1fr)`; `.locations__list` и `.reviews__list` — `repeat(3, 1fr)`; `.event__meta` — 2 колонки; `.reviews__top` — `1fr auto`; `grid-area` для `events`, `locations`, `reviews` |
| **Grid (планшет)** | то же, `@media (min-width:768px) and (max-width:1023px)` | отзывы в 2 колонки, площадки в 3 |
| **FlexBox (телефон)** | то же, `@media (max-width:767px)` | `.events__grid`, `.locations__list`, `.reviews__list` → `flex-direction:column`; `.event__meta` → `flex-wrap:wrap`; кнопки в столбик |
| **Общий каркас** | `css/layout.css` | `grid-template-areas` для `events`, `locations`, `reviews` (ПК и планшет), `order` на телефоне; стили `.card`, `.btn`, `.badge` |

## Связь с ТЗ по бэку и заданием преподавателя
- Карточка = `GET /events/{id}`: название, дата, длительность, цена, `maxPlaces` / `occupiedPlaces`, площадка, статус.
- «Записаться» = `POST /events/registrations/{eventId}`; «Отменить запись» = `DELETE /events/registrations/cancel/{eventId}`.
- «В список ожидания» — дополнение преподавателя: если `occupiedPlaces = maxPlaces`, вместо записи показывается список ожидания.
- Статусы `WAIT_START / STARTED / FINISHED / CANCELLED` + «ждёт подтверждения админа» (подтверждение создания/отмены).
- Площадки = `GET /locations`. Рейтинг и комментарии — требование преподавателя.

## Что уметь менять на защите

- **Карточки по 3 в ряд** → `.events__grid { grid-template-columns: repeat(3, 1fr); }`
- **Адаптивные карточки без media** → `repeat(auto-fill, minmax(250px, 1fr))`
- **Первая карточка на 2 колонки** → `.event:first-child { grid-column: span 2; }`
- **Изменить расстояние между карточками** → `gap: 30px;`
- **Кнопки в карточке в столбик** → `.event__actions { grid-auto-flow: row; }`
- **Выровнять карточки по высоте** → `align-items: stretch;`
- **На телефоне карточки в ряд с прокруткой** → `.events__grid { flex-direction: row; overflow-x: auto; }`
- **На телефоне 2 карточки в ряд** → `.events__grid { flex-direction: row; flex-wrap: wrap; }` + `.event { flex: 1 1 45%; }`
- **Поменять местами блоки на телефоне** → поменять `order` в `layout.css`

## Принципы, которые надо объяснить
- `grid-template-columns: repeat(2, 1fr)` — две равные колонки; `fr` делит свободное место.
- `grid-area` + `grid-template-areas` — размещение блока по имени области, удобно перестраивать страницу в media-запросе (на планшете области другие).
- `display:flex` + `flex-direction:column` — элементы идут столбиком; `flex-wrap:wrap` — переносятся на новую строку; `flex: 1 1 45%` — рост / сжатие / базовая ширина.
