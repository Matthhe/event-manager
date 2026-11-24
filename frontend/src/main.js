import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// Вёрстка из макета (Grid + Flexbox + media) и стили для новых элементов
import './assets/css/layout.css'
import './assets/css/header-search-footer.css'
import './assets/css/events-locations-reviews.css'
import './assets/css/sidebar-admin.css'
import './assets/css/app.css'

createApp(App).use(router).mount('#app')
