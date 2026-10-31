/**
 * Данные, загруженные до отрисовки страниц и сохранённые в состоянии Nuxt: запрос
 * выполняется, только когда состояние пусто.
 * Клиент получает значение от сервера из состояния и не повторяет запрос.
 */
export async function useStoredFetch<T>(key: string, load: () => Promise<T>): Promise<T> {
  const stored = useState<T | null>(key, () => null)

  if (stored.value !== null) {
    return stored.value
  }

  const data = await load()

  stored.value = data

  return data
}
