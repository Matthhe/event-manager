// Подписи статусов и форматирование значений для интерфейса

// [текст, модификатор бейджа]
export const EVENT_STATUS = {
  PENDING: ['Ждёт подтверждения админа', 'warn'],
  REJECTED: ['Отклонено админом', 'bad'],
  WAIT_START: ['Ожидает начала', 'ok'],
  STARTED: ['Идёт', ''],
  FINISHED: ['Завершено', ''],
  CANCELLED: ['Отменено', 'bad'],
}

export const HISTORY_STATUS = {
  REGISTERED: ['Записан', 'ok'],
  WAITLIST: ['В списке ожидания', 'warn'],
  ATTENDED: ['Посетил', ''],
  CANCELLED: ['Запись отменена', 'bad'],
  EVENT_CANCELLED: ['Мероприятие отменено', 'bad'],
}

export const NOTIFICATION_TYPE = {
  EVENT_UPDATED: ['Изменено', 'warn'],
  EVENT_CANCELLED: ['Отмена', 'bad'],
  EVENT_STARTED: ['Началось', 'ok'],
  EVENT_FINISHED: ['Завершено', ''],
  WAITLIST_PROMOTED: ['Лист ожидания', 'ok'],
  REQUEST_APPROVED: ['Одобрено', 'ok'],
  REQUEST_REJECTED: ['Отклонено', 'bad'],
}

export const FIELD_NAMES = {
  name: 'название',
  description: 'описание',
  date: 'начало',
  duration: 'длительность, мин',
  cost: 'цена',
  maxPlaces: 'мест',
  locationId: 'площадка',
  status: 'статус',
}

/** Класс бейджа: badge badge--ok */
export function badgeClass(mod) {
  return mod ? `badge badge--${mod}` : 'badge'
}

/** 2026-11-15T19:00:00+03:00 -> 15.11.2026, 19:00 */
export function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

/** Значение поля из списка изменений уведомления */
export function formatChange(field, value) {
  if (field === 'date') return formatDate(value)
  if (field === 'status') return EVENT_STATUS[value]?.[0] ?? value
  return value
}

export function formatCost(cost) {
  return Number(cost) === 0 ? 'Бесплатно' : `${cost} BYN`
}

export function stars(n) {
  return '★'.repeat(n) + '☆'.repeat(5 - n)
}

/** ISO -> значение для <input type="datetime-local"> (местное время) */
export function toLocalInput(iso) {
  const d = new Date(iso)
  const pad = (x) => String(x).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** значение <input type="datetime-local"> -> ISO с часовым поясом */
export function fromLocalInput(value) {
  return new Date(value).toISOString()
}
