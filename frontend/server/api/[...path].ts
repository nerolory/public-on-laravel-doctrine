/** Прокси запросов /api к бэкенду; адрес задаёт переменная окружения NUXT_API_UPSTREAM. */
export default defineEventHandler((event) => {
  const { apiUpstream } = useRuntimeConfig(event)

  if (!apiUpstream) {
    throw createError({ statusCode: 500, message: 'Не задан адрес API: переменная NUXT_API_UPSTREAM' })
  }

  return proxyRequest(event, `${apiUpstream}${event.path}`, {
    headers: { accept: getHeader(event, 'accept') || 'application/json' },
    fetchOptions: { redirect: 'manual' },
  })
})
