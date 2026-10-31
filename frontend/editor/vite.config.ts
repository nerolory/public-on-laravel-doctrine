import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'

/** Файл точки входа в editor/. */
const entry = (file: string) => fileURLToPath(new URL(file, import.meta.url))

/**
 * Ассеты админки MoonShine: общие (admin.ts) и редактор формы статьи (article-editor.ts).
 * Сборка с манифестом попадает в editor/dist (смонтирована как public/build/admin, см. compose.yaml),
 * страницы подключают её через Illuminate\Foundation\Vite.
 */
export default defineConfig({
  root: fileURLToPath(new URL('.', import.meta.url)),
  base: '/build/admin/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: { 'admin': entry('admin.ts'), 'article-editor': entry('article-editor.ts') },
    },
  },
})
