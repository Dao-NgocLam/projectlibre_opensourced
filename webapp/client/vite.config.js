import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

// Base './' để chạy tốt trên mọi sub-path của shared hosting.
export default defineConfig({
  base: './',
  plugins: [svelte()],
  server: {
    port: 5173,
    proxy: { '/api': 'http://localhost:8080' }, // dev local với `php -S localhost:8080 -t ../`
  },
  build: { outDir: 'dist', assetsInlineLimit: 4096, chunkSizeWarningLimit: 700 },
});
