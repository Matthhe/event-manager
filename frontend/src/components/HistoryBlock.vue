<script setup>
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import { HISTORY_STATUS, badgeClass, formatDate } from '../utils/format'

const props = defineProps({ refreshKey: { type: Number, default: 0 } })
const items = ref([])

async function load() {
  items.value = await api('GET', '/events/registrations/my')
}

onMounted(load)
watch(() => props.refreshKey, load)
</script>

<template>
  <section class="history block" id="history">
    <h2>Моя история</h2>
    <p v-if="!items.length" class="muted">Вы ещё никуда не записывались</p>
    <ul>
      <li v-for="h in items" :key="h.eventId" class="history__item">
        <span>
          <router-link :to="`/events/${h.eventId}`">{{ h.eventName }}</router-link>
          <router-link v-if="h.canReview" class="history__review" :to="`/events/${h.eventId}#review-form`">
            оставить отзыв
          </router-link>
        </span>
        <span :class="badgeClass(HISTORY_STATUS[h.historyStatus][1])">
          {{ HISTORY_STATUS[h.historyStatus][0] }}<template v-if="h.waitlistPosition"> (№{{ h.waitlistPosition }})</template>
        </span>
        <span class="history__date">{{ formatDate(h.date) }} · {{ h.locationName }}</span>
      </li>
    </ul>
  </section>
</template>
