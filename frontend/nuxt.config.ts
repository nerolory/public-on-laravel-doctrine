export default defineNuxtConfig({
  modules: ['@nuxt/fonts', '@nuxt/eslint'],
  // vue-i18n подключён плагином без модуля @nuxtjs/i18n: языки и режим адресов приходят с бэкенда во время работы.
  imports: { presets: [{ from: 'vue-i18n', imports: ['useI18n'] }] },
  devtools: { enabled: false },
  app: {
    head: {
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
  css: ['~/assets/scss/main.scss'],
  // Адрес бэкенда для прокси /api на сервере; значение задаёт NUXT_API_UPSTREAM. Пустое значение — ошибка запроса.
  runtimeConfig: {
    apiUpstream: '',
  },
  compatibilityDate: '2026-10-29',
  vite: {
    // TipTap и Chart.js перечислены заранее, чтобы Vite не пересобирал зависимости и не перезагружал страницу
    // при первом открытии редактора или статьи с графиком.
    optimizeDeps: {
      include: [
        'vue-i18n',
        '@tiptap/core',
        '@tiptap/starter-kit',
        '@tiptap/extension-image',
        '@tiptap/extension-table',
        '@tiptap/pm/state',
        'chart.js',
      ],
    },
  },
  hooks: {
    // Ссылки prefetch для CSS и скриптов не выводятся: в виде rel="prefetch" as="…" они не проходят проверку HTML.
    'build:manifest': (manifest) => {
      for (const item of Object.values(manifest)) {
        item.prefetch = false
      }
    },
  },
  eslint: { config: { stylistic: { indent: 2, quotes: 'single', semi: false } } },
  // Шрифты скачиваются при сборке и раздаются с самого сайта (/_fonts): посетитель не обращается к сторонним серверам.
  fonts: {
    families: [
      { name: 'Manrope', provider: 'fontsource', weights: [400, 500, 600, 700, 800], subsets: ['latin', 'cyrillic'] },
      { name: 'DM Sans', provider: 'fontsource', weights: [400, 500, 600, 700], subsets: ['latin'] },
    ],
    providers: { google: false, googleicons: false, bunny: false, fontshare: false, adobe: false },
  },
})
