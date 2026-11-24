<script setup>
// Создание (/events/new) и редактирование (/events/:id/edit) мероприятия
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { isAdmin } from '../store/auth'
import { fromLocalInput, toLocalInput } from '../utils/format'

const props = defineProps({ id: { type: String, default: null } })
const router = useRouter()
const locations = ref([])
const error = ref('')
const form = reactive({
  name: '', description: '', date: '', duration: 60, cost: 0, maxPlaces: 10, locationId: '',
})

onMounted(async () => {
  locations.value = await api('GET', '/locations')
  if (props.id) {
    const e = await api('GET', `/events/${props.id}`)
    Object.assign(form, {
      name: e.name, description: e.description, date: toLocalInput(e.date),
      duration: e.duration, cost: e.cost, maxPlaces: e.maxPlaces, locationId: e.locationId,
    })
  }
})

async function submit() {
  error.value = ''
  const body = { ...form, date: fromLocalInput(form.date) }
  try {
    const saved = props.id
      ? await api('PUT', `/events/${props.id}`, body)
      : await api('POST', '/events', body)
    router.push(`/events/${saved.id}`)
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <section class="view block form-page">
    <h1>{{ id ? 'Редактирование мероприятия' : 'Новое мероприятие' }}</h1>
    <p v-if="!id && !isAdmin()" class="muted">После создания мероприятие появится в каталоге, когда его подтвердит администратор.</p>
    <form class="form" @submit.prevent="submit">
      <label class="field">Название <input v-model.trim="form.name" required minlength="3"></label>
      <label class="field">Описание <textarea v-model.trim="form.description" rows="4"></textarea></label>
      <label class="field">Дата и время начала <input v-model="form.date" type="datetime-local" required></label>
      <label class="field">Длительность, мин <input v-model.number="form.duration" type="number" min="10" required></label>
      <label class="field">Цена, BYN <input v-model.number="form.cost" type="number" min="0" step="0.01" required></label>
      <label class="field">Площадка
        <select v-model.number="form.locationId" required>
          <option value="" disabled>Выберите площадку</option>
          <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }} (до {{ l.capacity }} мест)</option>
        </select>
      </label>
      <label class="field">Количество мест <input v-model.number="form.maxPlaces" type="number" min="1" required></label>
      <p v-if="error" class="msg msg--error">{{ error }}</p>
      <button class="btn" type="submit">{{ id ? 'Сохранить' : 'Создать' }}</button>
    </form>
  </section>
</template>
