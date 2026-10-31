import type { ListPage } from '#shared/types/content'

/**
 * Список с подгрузкой следующих страниц. source — первая страница, пришедшая с родителем (null, пока её нет);
 * load — запрос страницы по номеру. При смене source список и номер страницы сбрасываются, поэтому фильтр или автор
 * заменяют элементы целиком. Список без источника открывается через restart: первая страница запрашивается по действию.
 */
export function usePagedList<T>(
  source: () => ListPage<T> | null | undefined,
  load: (page: number) => Promise<ListPage<T>>,
  failureKey: string,
) {
  const items = shallowRef<T[]>([])
  const hasMore = ref(false)
  /** Номер последней загруженной страницы; 0 — страниц ещё нет. */
  const page = ref(0)
  const { busy, error, run, reset: resetNotice } = useFormAction()

  /** Очищает элементы, номер страницы и сообщение о результате. */
  function reset() {
    items.value = []
    hasMore.value = false
    page.value = 0
    resetNotice()
  }

  watch(source, (value) => {
    reset()

    if (value) {
      items.value = value.items
      hasMore.value = value.has_more
      page.value = 1
    }
  }, { immediate: true })

  /** Загружает страницу с номером number и дописывает её элементы к previous; возвращает, удалась ли загрузка. */
  function loadPage(number: number, previous: T[]): Promise<boolean> {
    return run(async () => {
      const result = await load(number)

      items.value = [...previous, ...result.items]
      hasMore.value = result.has_more
      page.value = number
    }, { failure: failureKey })
  }

  /** Загружает следующую страницу и дописывает её элементы. */
  async function more(): Promise<void> {
    if (busy.value || !hasMore.value) {
      return
    }

    await loadPage(page.value + 1, items.value)
  }

  /** Загружает первую страницу заново и заменяет ей элементы; возвращает, удалась ли загрузка. */
  async function restart(): Promise<boolean> {
    reset()

    return loadPage(1, [])
  }

  /** Добавляет новый элемент в начало списка. */
  function prepend(item: T): void {
    items.value = [item, ...items.value]
  }

  /** Добавляет элемент в конец списка. */
  function append(item: T): void {
    items.value = [...items.value, item]
  }

  return { items, hasMore, busy, error, more, restart, reset, prepend, append }
}
