<script setup>
// Просмотр площадки и ближайших мероприятий на ней; админ может изменить или удалить площадку
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { isAdmin } from '../store/auth'
import { EVENT_STATUS, badgeClass, formatDate } from '../utils/format'

const props = defineProps({ id: { type: String, required: true } })
const router = useRouter()
const location = ref(null)
const error = ref('')

async function load() {
  try {
    location.value = await api('GET', `/locations/${props.id}`)
  } catch (e) {
    error.value = e.message
  }
}

async function remove() {
  if (!confirm(`Удалить площадку «${location.value.name}»?`)) return
  error.value = ''
  try {
    await api('DELETE', `/locations/${props.id}`)
    router.push({ path: '/', hash: '#locations' })
  } catch (e) {
    error.value = e.message // например, на площадке уже есть мероприятия
  }
}

onMounted(load)
watch(() => props.id, load)
</script>

<template>
  <section class="view block">
    <p v-if="error" class="msg msg--error">{{ error }}</p>
    <template v-if="location">
      <h1>{{ location.name }}</h1>
      <p>📍 {{ location.address }}</p>
      <p>👥 Вместимость: {{ location.capacity }} мест</p>
      <p>{{ location.description }}</p>
      <div v-if="isAdmin()" class="event-page__manage">
        <router-link class="btn btn--light" :to="`/locations/${location.id}/edit`">Изменить</router-link>
        <button class="btn btn--danger" type="button" @click="remove">Удалить</button>
      </div>
      <h2 class="mt">Ближайшие мероприятия</h2>
      <p v-if="!location.upcomingEvents.length" class="muted">Запланированных мероприятий нет</p>
      <ul>
        <li v-for="e in location.upcomingEvents" :key="e.id" class="history__item">
          <router-link :to="`/events/${e.id}`">{{ e.name }}</router-link>
          <span :class="badgeClass(EVENT_STATUS[e.status][1])">{{ EVENT_STATUS[e.status][0] }}</span>
          <span class="history__date">{{ formatDate(e.date) }}</span>
        </li>
      </ul>
    </template>
  </section>
</template>
