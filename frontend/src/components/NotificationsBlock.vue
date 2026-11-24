<script setup>
import { onMounted, ref, watch } from 'vue'
import { api, refreshUnread } from '../api'
import { NOTIFICATION_TYPE, FIELD_NAMES, badgeClass, formatChange, formatDate } from '../utils/format'

const props = defineProps({ refreshKey: { type: Number, default: 0 } })
const items = ref([])
const error = ref('')

async function load() {
  try {
    items.value = await api('GET', '/notifications?size=10')
  } catch (e) {
    error.value = e.message
  }
}

async function markAllRead() {
  await api('POST', '/notifications/read', {})
  await load()
  refreshUnread()
}

onMounted(load)
watch(() => props.refreshKey, load)
</script>

<template>
  <section class="notifications block" id="notifications">
    <h2>Уведомления</h2>
    <p v-if="error" class="msg msg--error">{{ error }}</p>
    <p v-else-if="!items.length" class="muted">Уведомлений пока нет</p>
    <ul>
      <li v-for="n in items" :key="n.id" :class="['notif', { 'notif--unread': !n.isRead }]">
        <span>
          <router-link v-if="n.eventId" :to="`/events/${n.eventId}`">{{ n.message }}</router-link>
          <template v-else>{{ n.message }}</template>
        </span>
        <span :class="badgeClass(NOTIFICATION_TYPE[n.type]?.[1])">{{ NOTIFICATION_TYPE[n.type]?.[0] ?? n.type }}</span>
        <span v-for="c in n.changes" :key="c.field" class="notif__change">
          {{ FIELD_NAMES[c.field] ?? c.field }}: {{ formatChange(c.field, c.oldValue) }} → {{ formatChange(c.field, c.newValue) }}
        </span>
        <span class="notif__change">{{ formatDate(n.createdAt) }}</span>
      </li>
    </ul>
    <button v-if="items.some((n) => !n.isRead)" class="btn notifications__read" type="button" @click="markAllRead">
      Отметить прочитанными
    </button>
  </section>
</template>
