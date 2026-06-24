import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    emptyOutDir: false,
  },
  server: {
    proxy: {
      '/client-api': {
        target: 'http://localhost/tfeope-api',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/client-api/, ''),
        cookiePathRewrite: {
          '*': '/',
        },
      },
    },
  },
})
