<script setup>
import { onMounted } from 'vue'
import AppHeader from './components/AppHeader.vue'
import AppFooter from './components/AppFooter.vue'
import { refreshUnread } from './api'

onMounted(refreshUnread)
</script>

<template>
  <!-- .page — главный Grid из layout.css; блоки страниц становятся его прямыми потомками -->
  <div class="page">
    <AppHeader />
    <router-view />
    <AppFooter />
  </div>
</template>
