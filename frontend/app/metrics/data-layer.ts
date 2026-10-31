import { ensureWindowGlobal } from './globals'

/** Общая очередь событий Google: её читают gtag.js и gtm.js. */
export function dataLayer(): unknown[] {
  return ensureWindowGlobal('dataLayer', () => [])
}
