import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/build/',
  resolve: {
    alias: {
      '@': '/assets',
    },
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
  },
})
