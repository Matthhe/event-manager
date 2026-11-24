<script setup>
// Мероприятия, которые создал текущий пользователь (включая ждущие подтверждения и отклонённые)
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { EVENT_STATUS, badgeClass, formatDate } from '../utils/format'

const events = ref([])
onMounted(async () => {
  events.value = await api('GET', '/events/my')
})
</script>

<template>
  <section class="view block">
    <div class="reviews__top">
      <h1>Мои мероприятия</h1>
      <router-link class="btn" to="/events/new">+ Создать</router-link>
    </div>
    <p v-if="!events.length" class="muted">Вы ещё не создавали мероприятий</p>
    <ul>
      <li v-for="e in events" :key="e.id" class="admin__item">
        <div>
          <strong><router-link :to="`/events/${e.id}`">{{ e.name }}</router-link></strong><br>
          <span class="admin__type">
            {{ formatDate(e.date) }} · {{ e.locationName }} · записано {{ e.occupiedPlaces }} из {{ e.maxPlaces }}
            <template v-if="e.waitlistCount"> · в ожидании {{ e.waitlistCount }}</template>
          </span>
        </div>
        <div class="admin__buttons">
          <span v-if="e.pendingRequest === 'CANCEL'" class="badge badge--warn">Заявка на отмену</span>
          <span :class="badgeClass(EVENT_STATUS[e.status][1])">{{ EVENT_STATUS[e.status][0] }}</span>
        </div>
      </li>
    </ul>
  </section>
</template>
