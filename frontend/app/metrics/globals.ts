import type { MetaPixel, YandexMetrika } from './types'

declare global {
  interface Window {
    /** Очередь событий Google: её читают gtag.js и gtm.js. */
    dataLayer?: unknown[]
    /** Функция Яндекс Метрики: её вызовы читает tag.js. */
    ym?: YandexMetrika
    /** Очередь событий пикселя VK: её читает top.mail.ru. */
    _tmr?: unknown[]
    /** Пиксель Meta: его вызывает код вставки. */
    fbq?: MetaPixel
    /** Тот же пиксель под вторым именем: его читает fbevents.js. */
    _fbq?: MetaPixel
  }
}

/** Имена глобальных переменных счётчиков; у каждого имени свой тип в интерфейсе Window. */
type WindowGlobalName = 'dataLayer' | 'ym' | '_tmr' | 'fbq' | '_fbq'

/** Значение глобальной переменной, если она уже есть. */
export function windowGlobal<K extends WindowGlobalName>(name: K): Window[K] {
  return window[name]
}

/** Глобальная переменная: существующая или созданная при первом обращении. */
export function ensureWindowGlobal<K extends WindowGlobalName>(
  name: K,
  create: () => NonNullable<Window[K]>,
): NonNullable<Window[K]> {
  const existing = window[name]

  if (existing !== undefined) {
    return existing
  }

  const created = create()

  Object.assign(window, { [name]: created })

  return created
}
