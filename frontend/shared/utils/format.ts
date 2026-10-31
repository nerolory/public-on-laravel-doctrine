/**
 * Дата без времени (YYYY-MM-DD) на языке страницы. Часовой пояс — UTC, чтобы дата не сдвигалась
 * у посетителя западнее UTC. Метки времени выводит компонент NuxtTime.
 */
export function formatDate(value: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(value))
}

/** Первая буква имени для аватара без картинки. */
export function initialOf(name: string): string {
  return name.slice(0, 1).toUpperCase()
}
