<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, refreshUnread } from '../api'
import { setSession } from '../store/auth'

const router = useRouter()
const route = useRoute()
const form = reactive({ login: '', password: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    const { token, user } = await api('POST', '/users/auth', form)
    setSession(token, user)
    refreshUnread()
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <section class="view block form-page">
    <h1>Вход</h1>
    <form class="form" @submit.prevent="submit">
      <label class="field">Логин <input v-model.trim="form.login" required autocomplete="username"></label>
      <label class="field">Пароль <input v-model="form.password" type="password" required autocomplete="current-password"></label>
      <p v-if="error" class="msg msg--error">{{ error }}</p>
      <button class="btn" type="submit">Войти</button>
      <p class="muted">Нет аккаунта? <router-link to="/register">Зарегистрироваться</router-link></p>
    </form>
  </section>
</template>
