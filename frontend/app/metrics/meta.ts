import { ensureWindowGlobal, windowGlobal } from './globals'
import { loadScript } from './script'
import type { CounterDriver, Goal, MetaPixel } from './types'

/** Стандартные события Meta для целей сайта. */
const EVENTS: Record<Goal, [string, string]> = {
  registration: ['track', 'CompleteRegistration'],
  article_view: ['trackCustom', 'ArticleView'],
}

/** Версия протокола пикселя из официального кода вставки. */
const PIXEL_VERSION = '2.0'

/** Заглушка пикселя по образцу официального кода вставки; скрипт загружается при её создании. */
function install(src: string): MetaPixel {
  const fbq: MetaPixel = Object.assign((...args: unknown[]) => {
    if (fbq.callMethod) {
      fbq.callMethod(...args)

      return
    }

    fbq.queue.push(args)
  }, { queue: [], loaded: true, version: PIXEL_VERSION })

  fbq.push = fbq
  ensureWindowGlobal('_fbq', () => fbq)
  loadScript(src)

  return fbq
}

/** Пиксель Meta. */
export const meta: CounterDriver = {
  init(id, src) {
    ensureWindowGlobal('fbq', () => install(src))('init', id)
  },
  page: () => windowGlobal('fbq')?.('track', 'PageView'),
  goal: (_, goal) => windowGlobal('fbq')?.(...EVENTS[goal]),
}
