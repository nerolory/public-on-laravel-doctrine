type LocalizationMode = 'single' | 'prefix' | 'subdomain'
type Language = { code: string, name: string }
export type LocalizationConfig = {
  mode: LocalizationMode
  base_domain: string | null
  default: string
  languages: Language[]
}

/** Ключ общего состояния с кодом языка страницы: его определяет плагин i18n по адресу запроса. */
export const LOCALE_STATE = 'i18n-locale'

/**
 * Языки сайта из настроек бэкенда; конфигурацию загружает плагин i18n до отрисовки страниц.
 * Фронтенд только применяет её к адресам и запросам.
 */
export function useLocalization() {
  const config = useNuxtApp().$localization
  const locale = useState<string>(LOCALE_STATE, () => config.default)
  /** В режиме префикса адреса неосновного языка начинаются с его кода. */
  const isPrefixed = computed(() => config.mode === 'prefix' && locale.value !== config.default)

  /** Адрес внутри текущего языка: в режиме префикса добавляет код языка к пути. */
  function localePath(path: string): string {
    if (!isPrefixed.value || !path.startsWith('/')) {
      return path
    }

    return `/${locale.value}${path === '/' ? '' : path}`
  }

  /** Адрес той же страницы на другом языке; ведёт на полную загрузку, чтобы язык применился и на сервере. */
  function switchUrl(code: string, path: string): string {
    const bare = isPrefixed.value ? (path.slice(locale.value.length + 1) || '/') : path

    if (config.mode === 'subdomain' && config.base_domain) {
      const url = useRequestURL()
      const host = code === config.default ? config.base_domain : `${code}.${config.base_domain}`

      return `${url.protocol}//${host}${url.port ? `:${url.port}` : ''}${bare}`
    }

    return code === config.default ? bare : `/${code}${bare === '/' ? '' : bare}`
  }

  return { config, locale, localePath, switchUrl }
}
