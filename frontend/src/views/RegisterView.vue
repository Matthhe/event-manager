<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { setSession } from '../store/auth'

const router = useRouter()
const form = reactive({ login: '', password: '', name: '', age: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await api('POST', '/users', { ...form, age: form.age ? Number(form.age) : null })
    // сразу входим под новым пользователем
    const { token, user } = await api('POST', '/users/auth', { login: form.login, password: form.password })
    setSession(token, user)
    router.push('/')
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <section class="view block form-page">
    <h1>Регистрация</h1>
    <form class="form" @submit.prevent="submit">
      <label class="field">Логин (латиница, цифры, _) <input v-model.trim="form.login" required minlength="3"></label>
      <label class="field">Пароль (от 6 символов) <input v-model="form.password" type="password" required minlength="6"></label>
      <label class="field">Имя <input v-model.trim="form.name" required></label>
      <label class="field">Возраст <input v-model="form.age" type="number" min="0"></label>
      <p v-if="error" class="msg msg--error">{{ error }}</p>
      <button class="btn" type="submit">Зарегистрироваться</button>
    </form>
  </section>
</template>
