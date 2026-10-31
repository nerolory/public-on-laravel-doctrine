import { createI18n } from 'vue-i18n'
import type { LocaleMessageValue, VueMessageType } from 'vue-i18n'
import { LOCALE_STATE } from '~/composables/useLocalization'
import type { LocalizationConfig } from '~/composables/useLocalization'

type Messages = Record<string, LocaleMessageValue<VueMessageType>>

/**
 * Языки и переводы берутся с бэкенда. Язык определяется по адресу — префиксу /en/ или поддомену en.example.com —
 * в зависимости от режима из настроек сайта. Конфигурация языков доступна как $localization.
 */
export default defineNuxtPlugin({
  name: 'i18n',
  async setup(nuxtApp) {
    const localization = await useStoredFetch<LocalizationConfig>(
      'i18n-config',
      () => $fetch<LocalizationConfig>(apiPaths.localization),
    )
    const locale = useState<string>(LOCALE_STATE, () => detectLocale(localization, useRequestURL()))
    const messages = await useStoredFetch<Messages>(
      'i18n-messages',
      () => $fetch<Messages>(apiPaths.translations(locale.value)),
    )

    nuxtApp.vueApp.use(createI18n({
      legacy: false,
      globalInjection: true,
      locale: locale.value,
      fallbackLocale: localization.default,
      messages: { [locale.value]: messages },
    }))

    return { provide: { localization } }
  },
})

/** Код языка из адреса: префикс пути или поддомен; без них — язык по умолчанию. */
function detectLocale(config: LocalizationConfig, url: URL): string {
  const codes = config.languages.map(language => language.code)

  if (config.mode === 'prefix') {
    const first = url.pathname.split('/')[1] ?? ''

    if (codes.includes(first)) {
      return first
    }
  }

  if (config.mode === 'subdomain' && config.base_domain) {
    const host = url.hostname
    const code = host.endsWith(`.${config.base_domain}`) ? host.slice(0, -config.base_domain.length - 1) : ''

    if (codes.includes(code)) {
      return code
    }
  }

  return config.default
}
