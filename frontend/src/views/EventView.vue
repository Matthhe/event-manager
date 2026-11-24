<script setup>
// Описание мероприятия: подробности, запись / список ожидания, управление для владельца, отзывы
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../api'
import { auth, isAdmin } from '../store/auth'
import EventCard from '../components/EventCard.vue'
import { EVENT_STATUS, badgeClass, stars } from '../utils/format'

const props = defineProps({ id: { type: String, required: true } })
const event = ref(null)
const reviews = ref({ rating: null, count: 0, items: [] })
const waitlist = ref(null)
const error = ref('')
const message = ref('')
const reviewForm = reactive({ rating: 5, comment: '' })
const reviewError = ref('')

const canManage = computed(() => event.value && auth.user &&
  (isAdmin() || event.value.ownerId === auth.user.id))
const canReview = computed(() => event.value?.status === 'FINISHED' &&
  event.value.myRegistrationStatus === 'ACTIVE' &&
  !reviews.value.items.some((r) => r.userId === auth.user?.id))

async function load() {
  error.value = ''
  try {
    event.value = await api('GET', `/events/${props.id}`)
    reviews.value = await api('GET', `/events/${props.id}/reviews`)
    waitlist.value = auth.token && event.value.waitlistCount
      ? await api('GET', `/events/${props.id}/waitlist`)
      : null
  } catch (e) {
    error.value = e.message
  }
}

async function cancelEvent() {
  if (!confirm('Отменить мероприятие?')) return
  try {
    message.value = (await api('DELETE', `/events/${props.id}`)).message
    load()
  } catch (e) {
    error.value = e.message
  }
}

async function sendReview() {
  reviewError.value = ''
  try {
    await api('POST', `/events/${props.id}/reviews`, reviewForm)
    reviewForm.comment = ''
    load()
  } catch (e) {
    reviewError.value = e.message
  }
}

async function deleteReview(id) {
  await api('DELETE', `/reviews/${id}`)
  load()
}

onMounted(load)
watch(() => props.id, load)
</script>

<template>
  <section class="view block event-page">
    <p v-if="error" class="msg msg--error">{{ error }}</p>
    <template v-if="event">
      <div class="event-page__top">
        <h1>{{ event.name }}</h1>
        <span :class="badgeClass(EVENT_STATUS[event.status][1])">{{ EVENT_STATUS[event.status][0] }}</span>
      </div>
      <p class="muted">Организатор: {{ event.ownerName }} · Площадка: {{ event.locationName }}, {{ event.locationAddress }}</p>
      <p v-if="event.pendingRequest" class="msg msg--warn">
        {{ event.pendingRequest === 'CREATE' ? 'Ждёт подтверждения создания администратором' : 'Отправлена заявка на отмену — ждёт решения администратора' }}
      </p>

      <EventCard :event="event" @changed="load" />

      <div v-if="waitlist" class="waitlist card">
        <h3>Список ожидания: {{ waitlist.count }}</h3>
        <p v-if="waitlist.myPosition">Ваш номер в очереди: {{ waitlist.myPosition }}</p>
        <ol v-if="waitlist.users">
          <li v-for="u in waitlist.users" :key="u.userId">{{ u.name }}</li>
        </ol>
      </div>

      <div v-if="canManage && ['PENDING', 'WAIT_START'].includes(event.status)" class="event-page__manage">
        <router-link class="btn btn--light" :to="`/events/${event.id}/edit`">Редактировать</router-link>
        <button class="btn btn--danger" type="button" :disabled="event.pendingRequest === 'CANCEL'" @click="cancelEvent">
          Отменить мероприятие
        </button>
      </div>
      <p v-if="message" class="msg msg--ok">{{ message }}</p>

      <div class="reviews__top">
        <h2>Отзывы ({{ reviews.count }})</h2>
        <span v-if="reviews.rating" class="reviews__rating">★ {{ reviews.rating }} / 5</span>
      </div>

      <form v-if="canReview" id="review-form" class="form review-form card" @submit.prevent="sendReview">
        <label class="field">Оценка
          <select v-model.number="reviewForm.rating">
            <option v-for="n in [5, 4, 3, 2, 1]" :key="n" :value="n">{{ stars(n) }}</option>
          </select>
        </label>
        <label class="field">Комментарий
          <textarea v-model.trim="reviewForm.comment" rows="3" required></textarea>
        </label>
        <p v-if="reviewError" class="msg msg--error">{{ reviewError }}</p>
        <button class="btn" type="submit">Оставить отзыв</button>
      </form>

      <div class="reviews__list">
        <article v-for="r in reviews.items" :key="r.id" class="review card">
          <p class="review__stars">{{ stars(r.rating) }}</p>
          <p>{{ r.comment }}</p>
          <p class="review__author">{{ r.author }}</p>
          <button
            v-if="auth.user && (isAdmin() || r.userId === auth.user.id)"
            class="link-btn" type="button" @click="deleteReview(r.id)"
          >Удалить</button>
        </article>
      </div>
    </template>
  </section>
</template>
