<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { stars } from '../utils/format'

const rating = ref(null)
const items = ref([])

onMounted(async () => {
  const data = await api('GET', '/reviews/latest?limit=6')
  rating.value = data.rating
  items.value = data.items
})
</script>

<template>
  <section class="reviews block" id="reviews">
    <div class="reviews__top">
      <h2>Рейтинг и комментарии</h2>
      <span v-if="rating" class="reviews__rating">★ {{ rating }} / 5</span>
    </div>
    <p v-if="!items.length" class="muted">Отзывов пока нет</p>
    <div class="reviews__list">
      <article v-for="r in items" :key="r.id" class="review card">
        <p class="review__stars">{{ stars(r.rating) }}</p>
        <p>{{ r.comment }}</p>
        <p class="review__author">
          {{ r.author }} · <router-link :to="`/events/${r.eventId}`">{{ r.eventName }}</router-link>
        </p>
      </article>
    </div>
  </section>
</template>
