<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, refreshUnread } from '../api'
import { auth } from '../store/auth'
import { EVENT_STATUS, badgeClass, formatDate, formatCost } from '../utils/format'

const props = defineProps({ event: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const router = useRouter()
const message = ref('')
const error = ref('')
const busy = ref(false)

const isFull = computed(() => props.event.occupiedPlaces >= props.event.maxPlaces)

// Бейдж: «Вы записаны» / «В списке ожидания» / «Мест нет» / статус мероприятия
const badge = computed(() => {
  const e = props.event
  if (e.status === 'WAIT_START') {
    if (e.myRegistrationStatus === 'ACTIVE') return ['Вы записаны', 'ok']
    if (e.myRegistrationStatus === 'WAITLIST') return ['Вы в списке ожидания', 'warn']
    if (isFull.value) return ['Мест нет', 'warn']
  }
  return EVENT_STATUS[e.status] ?? [e.status, '']
})

// Какую кнопку показать
const action = computed(() => {
  const e = props.event
  if (e.status === 'PENDING') return { text: 'Ожидает подтверждения', disabled: true }
  if (e.status === 'CANCELLED' || e.status === 'REJECTED') return { text: 'Недоступно', disabled: true }
  if (e.status !== 'WAIT_START') return { text: 'Запись закрыта', disabled: true }
  if (e.myRegistrationStatus === 'ACTIVE') return { text: 'Отменить запись', cls: 'btn--danger', run: cancel }
  if (e.myRegistrationStatus === 'WAITLIST') return { text: 'Выйти из списка ожидания', cls: 'btn--danger', run: cancel }
  if (isFull.value) return { text: 'В список ожидания', cls: 'btn--wait', run: register }
  return { text: 'Записаться', run: register }
})

async function call(fn) {
  if (!auth.token) {
    router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
    return
  }
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    await fn()
    emit('changed')
    refreshUnread()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function register() {
  return call(async () => {
    const res = await api('POST', `/events/registrations/${props.event.id}`)
    message.value = res.position ? `${res.message} (вы №${res.position})` : res.message
  })
}

function cancel() {
  return call(async () => {
    message.value = (await api('DELETE', `/events/registrations/cancel/${props.event.id}`)).message
  })
}
</script>

<template>
  <article class="event card">
    <div class="event__head">
      <h3 class="event__title">
        <router-link :to="`/events/${event.id}`">{{ event.name }}</router-link>
      </h3>
      <span :class="badgeClass(badge[1])">{{ badge[0] }}</span>
    </div>
    <p class="event__desc">{{ event.description }}</p>
    <div class="event__meta">
      <span>📅 {{ formatDate(event.date) }}</span>
      <span>⏱ {{ event.duration }} мин</span>
      <span>📍 <router-link :to="`/locations/${event.locationId}`">{{ event.locationName }}</router-link></span>
      <span>💶 {{ formatCost(event.cost) }}</span>
      <span>👥 Мест: {{ event.occupiedPlaces }} из {{ event.maxPlaces }}</span>
      <span v-if="event.waitlistCount">⏳ В списке ожидания: {{ event.waitlistCount }}</span>
      <span v-if="event.rating">★ {{ event.rating }} ({{ event.reviewsCount }})</span>
    </div>
    <div class="event__actions">
      <button
        :class="['btn', action.cls]"
        type="button"
        :disabled="action.disabled || busy"
        @click="action.run"
      >{{ action.text }}</button>
    </div>
    <p v-if="message" class="msg msg--ok">{{ message }}</p>
    <p v-if="error" class="msg msg--error">{{ error }}</p>
  </article>
</template>
