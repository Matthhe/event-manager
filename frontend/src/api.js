import { auth, clearSession } from './store/auth'

const BASE = import.meta.env.VITE_API_URL || '/api'

/**
 * Запрос к бэкенду. Возвращает JSON-ответ, при ошибке бросает Error с текстом от сервера.
 * Пример: const events = await api('POST', '/events/search', { name: 'Java' })
 */
export async function api(method, path, body) {
  const headers = { 'Content-Type': 'application/json' }
  if (auth.token) {
    headers.Authorization = `Bearer ${auth.token}`
  }
  const response = await fetch(BASE + path, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && auth.token) {
      clearSession() // токен истёк — выходим
    }
    throw new Error(data?.error || `Ошибка ${response.status}`)
  }
  return data
}

/** Обновить счётчик непрочитанных уведомлений в шапке */
export async function refreshUnread() {
  if (!auth.token) return
  try {
    auth.unread = (await api('GET', '/notifications/unread-count')).count
  } catch {
    auth.unread = 0
  }
}
