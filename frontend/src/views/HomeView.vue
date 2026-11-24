<script setup>
// Главная страница: те же блоки, что и в макете index.html, но с данными из API.
// У компонента несколько корневых элементов — они попадают прямо в Grid .page (grid-area из CSS).
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { auth, isAdmin } from '../store/auth'
import SearchForm from '../components/SearchForm.vue'
import EventCard from '../components/EventCard.vue'
import NotificationsBlock from '../components/NotificationsBlock.vue'
import HistoryBlock from '../components/HistoryBlock.vue'
import LocationsBlock from '../components/LocationsBlock.vue'
import ReviewsBlock from '../components/ReviewsBlock.vue'
import AdminBlock from '../components/AdminBlock.vue'

const PAGE_SIZE = 6
const locations = ref([])
const events = ref([])
const total = ref(0)
const page = ref(0)
const filters = ref({})
const loading = ref(false)
const error = ref('')
const refreshKey = ref(0) // при изменении — боковая панель перезагружает данные

async function loadEvents() {
  loading.value = true
  error.value = ''
  try {
    const data = await api('POST', '/events/search', { ...filters.value, page: page.value, size: PAGE_SIZE })
    events.value = data.items
    total.value = data.total
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function onSearch(newFilters) {
  filters.value = newFilters
  page.value = 0
  loadEvents()
}

function goToPage(p) {
  page.value = p
  loadEvents()
}

function onChanged() {
  loadEvents()
  refreshKey.value++
}

onMounted(async () => {
  loadEvents()
  locations.value = await api('GET', '/locations')
})
</script>

<template>
  <SearchForm :locations="locations" @search="onSearch" />

  <section class="events block" id="events">
    <h2>Мероприятия <span class="muted">({{ total }})</span></h2>
    <p v-if="error" class="msg msg--error">{{ error }}</p>
    <p v-else-if="!loading && !events.length" class="muted">Ничего не найдено</p>
    <div class="events__grid">
      <EventCard v-for="e in events" :key="e.id" :event="e" @changed="onChanged" />
    </div>
    <nav v-if="total > PAGE_SIZE" class="pagination" aria-label="Страницы">
      <button
        v-for="p in Math.ceil(total / PAGE_SIZE)"
        :key="p"
        type="button"
        :class="{ active: p - 1 === page }"
        @click="goToPage(p - 1)"
      >{{ p }}</button>
    </nav>
  </section>

  <aside v-if="auth.user" class="sidebar">
    <NotificationsBlock :refresh-key="refreshKey" />
    <HistoryBlock :refresh-key="refreshKey" />
  </aside>

  <LocationsBlock :locations="locations" />
  <ReviewsBlock />
  <AdminBlock v-if="isAdmin()" @changed="onChanged" />
</template>
