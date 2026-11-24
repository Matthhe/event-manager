import { reactive } from 'vue'

// Текущий пользователь и токен. Сохраняются в localStorage, чтобы не входить заново после перезагрузки.
export const auth = reactive({
  token: localStorage.getItem('token'),
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  unread: 0, // число непрочитанных уведомлений (для счётчика в шапке)
})

export function isAdmin() {
  return auth.user?.role === 'ADMIN'
}

export function setSession(token, user) {
  auth.token = token
  auth.user = user
  localStorage.setItem('token', token)
  localStorage.setItem('user', JSON.stringify(user))
}

export function clearSession() {
  auth.token = null
  auth.user = null
  auth.unread = 0
  localStorage.removeItem('token')
  localStorage.removeItem('user')
}
