import type { SiteConfig } from '~/composables/useSiteConfig'

/** Публичные настройки сайта загружаются до отрисовки страниц и доступны как $siteConfig. */
export default defineNuxtPlugin({
  name: 'site-config',
  dependsOn: ['i18n'],
  async setup() {
    const siteConfig = await useStoredFetch<SiteConfig>(
      'site-config',
      () => $fetch<SiteConfig>(apiPaths.site, { headers: useApiHeaders() }),
    )

    return { provide: { siteConfig } }
  },
})
