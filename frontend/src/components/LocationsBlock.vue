<script setup>
import { isAdmin } from '../store/auth'

defineProps({ locations: { type: Array, default: () => [] } })
</script>

<template>
  <section class="locations block" id="locations">
    <div class="reviews__top">
      <h2>Площадки</h2>
      <router-link v-if="isAdmin()" class="btn" to="/locations/new">+ Добавить площадку</router-link>
    </div>
    <div class="locations__list">
      <article v-for="l in locations" :key="l.id" class="location card">
        <h3 class="location__name"><router-link :to="`/locations/${l.id}`">{{ l.name }}</router-link></h3>
        <p>{{ l.address }}</p>
        <p>Вместимость: {{ l.capacity }} мест</p>
        <p>{{ l.description }}</p>
      </article>
    </div>
  </section>
</template>
