<script setup>
import { useRouter } from 'vue-router'
import { auth, clearSession } from '../store/auth'
import { api } from '../api'

const router = useRouter()

async function logout() {
  try {
    await api('POST', '/users/logout')
  } finally {
    clearSession()
    router.push('/')
  }
}
</script>

<template>
  <header class="header block">
    <router-link class="logo" to="/">EventHub</router-link>
    <nav class="nav">
      <ul class="nav__list">
        <li><router-link :to="{ path: '/', hash: '#events' }">Мероприятия</router-link></li>
        <li><router-link :to="{ path: '/', hash: '#locations' }">Площадки</router-link></li>
        <template v-if="auth.user">
          <li><router-link to="/my-events">Мои мероприятия</router-link></li>
          <li><router-link :to="{ path: '/', hash: '#history' }">Мои записи</router-link></li>
          <li>
            <router-link :to="{ path: '/', hash: '#notifications' }">
              Уведомления <span v-if="auth.unread" class="nav__count">{{ auth.unread }}</span>
            </router-link>
          </li>
        </template>
      </ul>
    </nav>
    <div class="auth">
      <template v-if="auth.user">
        <router-link class="btn" to="/events/new">+ Мероприятие</router-link>
        <span class="auth__name">{{ auth.user.name }}</span>
        <button class="btn btn--light" type="button" @click="logout">Выйти</button>
      </template>
      <template v-else>
        <router-link class="btn btn--light" to="/login">Войти</router-link>
        <router-link class="btn" to="/register">Регистрация</router-link>
      </template>
    </div>
  </header>
</template>
