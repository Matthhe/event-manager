<script setup>
import { reactive } from 'vue'

defineProps({ locations: { type: Array, default: () => [] } })
const emit = defineEmits(['search'])

// Поля = фильтры POST /api/events/search (все необязательны)
const filters = reactive({
  name: '',
  dateStartAfter: '',
  costMax: '',
  locationId: '',
  status: '',
  placeMin: '',
  durationMin: '',
})

function submit() {
  // пустые поля не отправляем
  const clean = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null))
  emit('search', clean)
}

function reset() {
  Object.keys(filters).forEach((k) => (filters[k] = ''))
  submit()
}
</script>

<template>
  <section class="search block">
    <h1>Найдите мероприятие</h1>
    <form class="search__form" @submit.prevent="submit">
      <label class="field">Название
        <input v-model.trim="filters.name" type="text" placeholder="Например, Митап по Java">
      </label>
      <label class="field">Дата начала от
        <input v-model="filters.dateStartAfter" type="date">
      </label>
      <label class="field">Цена до, BYN
        <input v-model="filters.costMax" type="number" min="0" placeholder="0">
      </label>
      <label class="field">Площадка
        <select v-model="filters.locationId">
          <option value="">Любая</option>
          <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </label>
      <label class="field">Статус
        <select v-model="filters.status">
          <option value="">Любой</option>
          <option value="WAIT_START">Ожидает начала</option>
          <option value="STARTED">Идёт</option>
          <option value="FINISHED">Завершено</option>
          <option value="CANCELLED">Отменено</option>
        </select>
      </label>
      <label class="field">Мест не меньше
        <input v-model="filters.placeMin" type="number" min="1" placeholder="5">
      </label>
      <label class="field">Длительность от, мин
        <input v-model="filters.durationMin" type="number" min="10" placeholder="10">
      </label>
      <div class="search__submit search__buttons">
        <button class="btn" type="submit">Найти</button>
        <button class="btn btn--light" type="button" @click="reset">Сбросить</button>
      </div>
    </form>
  </section>
</template>
