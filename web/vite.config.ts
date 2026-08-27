import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined
          }
          if (
            id.includes('react-router') ||
            id.includes('/react-dom/') ||
            id.includes('/react/') ||
            id.includes('/scheduler/')
          ) {
            return 'react-vendor'
          }
          if (id.includes('@tanstack/react-query')) {
            return 'query-vendor'
          }
          if (id.includes('i18next') || id.includes('react-i18next')) {
            return 'i18n-vendor'
          }
          if (id.includes('axios')) {
            return 'http-vendor'
          }

          return 'vendor'
        },
      },
    },
  },
  server: {
    host: true, // Listen on 0.0.0.0 so accessible from LAN
    port: 5173,
    proxy: {
      // Same machine as `php artisan serve` — use localhost so LAN IP changes don't break the proxy
      '/api': {
        target: 'http://192.168.187.197:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://192.168.187.197:8000',
        changeOrigin: true,
      },
    },
  },
})
