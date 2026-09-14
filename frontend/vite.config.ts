import path from 'node:path'

import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],

  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },

  server: {
    proxy: {
      '/wp-json': {
        target:
          process.env.VITE_WP_PROXY_TARGET ??
          'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
})