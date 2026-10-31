import { dataLayer } from './data-layer'
import { loadScript } from './script'
import type { CounterDriver } from './types'

/** Google Tag Manager: события уходят в dataLayer, теги настраиваются в самом GTM. */
export const gtm: CounterDriver = {
  init(_, src) {
    dataLayer().push({ 'gtm.start': Date.now(), 'event': 'gtm.js' })
    loadScript(src)
  },
  page: () => dataLayer().push({ event: 'page_view', page_path: location.pathname }),
  goal: (_, goal) => dataLayer().push({ event: goal }),
}
