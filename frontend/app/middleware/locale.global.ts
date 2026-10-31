/** Адрес с префиксом языка допустим только в режиме префикса и только для включённого языка, отличного от основного. */
export default defineNuxtRouteMiddleware((to) => {
  const prefix = to.params.locale

  if (typeof prefix !== 'string') {
    return
  }

  const { config } = useLocalization()

  if (config.mode === 'prefix' && prefix === config.default) {
    return navigateTo(to.fullPath.slice(prefix.length + 1) || '/', { redirectCode: 301 })
  }

  const known = config.languages.some(language => language.code === prefix)

  if (config.mode !== 'prefix' || !known) {
    throw createError({ statusCode: 404, statusMessage: 'Not Found' })
  }
})
