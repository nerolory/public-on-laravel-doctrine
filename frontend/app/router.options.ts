import type { RouterConfig } from '@nuxt/schema'

/**
 * Для режима префикса каждая страница доступна ещё и по адресу с кодом языка: /en/articles/1.
 * Список языков приходит с бэкенда, поэтому код задан шаблоном, а проверку выполняет middleware locale.global.
 */
export default <RouterConfig>{
  routes: routes => [
    ...routes,
    ...routes.map(route => ({
      ...route,
      path: `/:locale([a-z]{2}-[a-z]{2}|[a-z]{2})${route.path === '/' ? '' : route.path}`,
      name: route.name ? `${String(route.name)}___prefixed` : undefined,
    })),
  ],
}
