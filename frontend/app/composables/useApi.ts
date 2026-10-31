/** HTTP-метод запроса, меняющего данные. */
export type MutationMethod = 'POST' | 'PUT' | 'DELETE'

/** Тело запроса к API: поля формы или файл. */
export type RequestBody = Record<string, unknown> | FormData

/** Заголовки запроса к API: язык страницы, а при отрисовке на сервере ещё и cookie посетителя. */
export function useApiHeaders(): Record<string, string> {
  const { locale } = useLocalization()

  return {
    'Accept': 'application/json',
    'X-Locale': locale.value,
    ...(import.meta.server ? useRequestHeaders(['cookie']) : {}),
  }
}

/**
 * useFetch к API с языком текущей страницы. При отрисовке на сервере передаёт cookie посетителя,
 * чтобы автор видел свои неопубликованные статьи уже в первом HTML.
 */
export function useApiFetch<T>(
  url: string | (() => string),
  options: Omit<Exclude<Parameters<typeof useFetch<T>>[1], string>, 'headers'> = {},
) {
  return useFetch<T>(url, { ...options, headers: useApiHeaders() })
}

/** Запросы к API: чтение с языком страницы и изменения с CSRF-токеном. */
export function useApi() {
  const headers = useApiHeaders()

  /** Чтение данных API в браузере (подгрузка списков, окна). */
  function get<T>(path: string, query?: Record<string, string | number>): Promise<T> {
    return $fetch<T>(path, { query, headers })
  }

  /** Изменение данных: перед запросом берётся свежий CSRF-токен. */
  async function send<T = unknown>(path: string, method: MutationMethod, body?: RequestBody): Promise<T> {
    const { token } = await $fetch<{ token: string }>(apiPaths.csrf, { headers })

    return $fetch<T>(path, { method, body, headers: { ...headers, 'X-CSRF-TOKEN': token } })
  }

  return { get, send }
}
