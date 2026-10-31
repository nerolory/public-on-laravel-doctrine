import type { ResolvableLink, ResolvableMeta } from '@unhead/vue/types'

/** <head> страницы, собранный на сервере: title, meta, link и JSON-LD. Фронтенд их только выводит. */
export type ApiHead = {
  title: string
  meta: ResolvableMeta[]
  link: ResolvableLink[]
  json_ld: Record<string, unknown>[]
}

export function useApiHead(head: () => ApiHead | null | undefined) {
  useHead(() => {
    const value = head()

    if (!value) {
      return {}
    }

    return {
      title: value.title,
      meta: value.meta,
      link: value.link,
      // «<» экранируется, чтобы текст из JSON не мог закрыть тег script.
      script: value.json_ld.map(data => ({
        type: 'application/ld+json',
        textContent: JSON.stringify(data).replace(/</g, '\\u003c'),
      })),
    }
  })
}
