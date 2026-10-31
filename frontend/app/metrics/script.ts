/** Асинхронная загрузка внешнего скрипта счётчика. */
export function loadScript(src: string): void {
  const element = document.createElement('script')

  element.async = true
  element.src = src
  document.head.append(element)
}
