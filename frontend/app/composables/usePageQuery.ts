/**
 * Номер страницы списка из адреса ?page=. Значение, которое не число или меньше первой
 * страницы, открывает первую страницу.
 */
export function usePageQuery() {
  const route = useRoute()

  return computed(() => Math.max(1, Number(route.query.page) || 1))
}
