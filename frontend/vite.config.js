import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Запросы /api/... во время разработки перенаправляются на PHP-бэкенд (docker: порт 8080)
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8081',
    },
  },
})
