<script setup>
// Создание (/locations/new) и редактирование (/locations/:id/edit) площадки — только для админа
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const props = defineProps({ id: { type: String, default: null } })
const router = useRouter()
const error = ref('')
const form = reactive({ name: '', address: '', capacity: 20, description: '' })

onMounted(async () => {
  if (props.id) {
    const l = await api('GET', `/locations/${props.id}`)
    Object.assign(form, {
      name: l.name, address: l.address, capacity: l.capacity, description: l.description ?? '',
    })
  }
})

async function submit() {
  error.value = ''
  try {
    const saved = props.id
      ? await api('PUT', `/locations/${props.id}`, form)
      : await api('POST', '/locations', form)
    router.push(`/locations/${saved.id}`)
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <section class="view block form-page">
    <h1>{{ id ? 'Редактирование площадки' : 'Новая площадка' }}</h1>
    <form class="form" @submit.prevent="submit">
      <label class="field">Название <input v-model.trim="form.name" required></label>
      <label class="field">Адрес <input v-model.trim="form.address" required></label>
      <label class="field">Вместимость, мест (от 5) <input v-model.number="form.capacity" type="number" min="5" required></label>
      <label class="field">Описание <textarea v-model.trim="form.description" rows="3"></textarea></label>
      <p v-if="error" class="msg msg--error">{{ error }}</p>
      <button class="btn" type="submit">{{ id ? 'Сохранить' : 'Создать' }}</button>
    </form>
  </section>
</template>
