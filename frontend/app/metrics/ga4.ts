import { dataLayer } from './data-layer'
import { loadScript } from './script'
import type { CounterDriver, Goal } from './types'

/** Названия целей в терминах GA4. */
const EVENTS: Record<Goal, string> = { registration: 'sign_up', article_view: 'article_view' }

/**
 * gtag кладёт в очередь объект arguments: GA4 различает его и обычный массив.
 * Параметры описывают вызов для TypeScript, в очередь уходит сам объект arguments.
 */
function gtag(..._command: unknown[]): void {
  // eslint-disable-next-line prefer-rest-params
  dataLayer().push(arguments)
}

/** Google Analytics 4. */
export const ga4: CounterDriver = {
  init(id, src) {
    loadScript(src)
    gtag('js', new Date())
    gtag('config', id, { send_page_view: false })
  },
  page: () => gtag('event', 'page_view', { page_location: location.href, page_title: document.title }),
  goal: (_, goal) => gtag('event', EVENTS[goal]),
}
