<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import { formatDate } from '../utils/format'

const emit = defineEmits(['changed'])
const status = ref('PENDING')
const items = ref([])
const comments = ref({}) // причина отклонения для каждой заявки
const error = ref('')

async function load() {
  error.value = ''
  try {
    items.value = await api('GET', `/admin/requests?status=${status.value}`)
  } catch (e) {
    error.value = e.message
  }
}

async function decide(id, decision) {
  try {
    const body = decision === 'reject' ? { comment: comments.value[id] || null } : undefined
    await api('POST', `/admin/requests/${id}/${decision}`, body)
    await load()
    emit('changed')
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)
watch(status, load)
</script>

<template>
  <section class="admin block" id="admin">
    <h2>Панель администратора</h2>
    <label class="field admin__filter">Заявки
      <select v-model="status">
        <option value="PENDING">На рассмотрении</option>
        <option value="APPROVED">Подтверждённые</option>
        <option value="REJECTED">Отклонённые</option>
      </select>
    </label>
    <p v-if="error" class="msg msg--error">{{ error }}</p>
    <p v-else-if="!items.length" class="muted">Заявок нет</p>
    <ul>
      <li v-for="r in items" :key="r.id" class="admin__item">
        <div>
          <strong><router-link :to="`/events/${r.eventId}`">{{ r.eventName }}</router-link></strong><br>
          <span class="admin__type">
            {{ r.type === 'CREATE' ? 'Создание' : 'Отмена' }} · владелец: {{ r.requestedBy }} · {{ formatDate(r.eventDate) }}
          </span>
          <span v-if="r.comment" class="admin__type"><br>Комментарий: {{ r.comment }}</span>
        </div>
        <div v-if="r.status === 'PENDING'" class="admin__buttons">
          <input v-model="comments[r.id]" class="admin__comment" type="text" placeholder="Причина отказа">
          <button class="btn" type="button" @click="decide(r.id, 'approve')">Подтвердить</button>
          <button class="btn btn--danger" type="button" @click="decide(r.id, 'reject')">Отклонить</button>
        </div>
      </li>
    </ul>
  </section>
</template>
