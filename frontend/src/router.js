import { createRouter, createWebHistory } from 'vue-router'
import { auth, isAdmin } from './store/auth'

import HomeView from './views/HomeView.vue'
import LoginView from './views/LoginView.vue'
import RegisterView from './views/RegisterView.vue'
import EventView from './views/EventView.vue'
import EventFormView from './views/EventFormView.vue'
import MyEventsView from './views/MyEventsView.vue'
import LocationView from './views/LocationView.vue'
import LocationFormView from './views/LocationFormView.vue'

const routes = [
  { path: '/', component: HomeView },
  { path: '/login', component: LoginView },
  { path: '/register', component: RegisterView },
  { path: '/events/new', component: EventFormView, meta: { requiresAuth: true } },
  { path: '/events/:id', component: EventView, props: true },
  { path: '/events/:id/edit', component: EventFormView, props: true, meta: { requiresAuth: true } },
  { path: '/my-events', component: MyEventsView, meta: { requiresAuth: true } },
  { path: '/locations/new', component: LocationFormView, meta: { requiresAuth: true, requiresAdmin: true } },
  { path: '/locations/:id', component: LocationView, props: true },
  { path: '/locations/:id/edit', component: LocationFormView, props: true, meta: { requiresAuth: true, requiresAdmin: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  // ссылки вида /#events прокручивают к нужному блоку
  scrollBehavior(to) {
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
  },
})

// Страницы с meta.requiresAuth доступны только после входа, с meta.requiresAdmin — только админу
router.beforeEach((to) => {
  if (to.meta.requiresAuth && !auth.token) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (to.meta.requiresAdmin && !isAdmin()) {
    return { path: '/' }
  }
})

export default router
