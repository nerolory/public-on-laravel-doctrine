/** Счётчик из настроек сайта (/api/site): тип, идентификатор, категория согласия и адрес скрипта. */
export type Counter = { type: CounterType, id: string, consent: 'analytics' | 'marketing', script: string }

export type CounterType = 'yandex' | 'ga4' | 'gtm' | 'vk' | 'meta'

/** Цели, которые сайт отправляет в счётчики. */
export type Goal = 'registration' | 'article_view'

/** Подключение счётчика, просмотр страницы и достижение цели. */
export type CounterDriver = {
  init: (id: string, src: string) => void
  page: (id: string) => void
  goal: (id: string, goal: Goal) => void
}

/** Выбор посетителя в баннере cookies. */
export type Consent = { v: number, analytics: boolean, marketing: boolean }

/** Функция Яндекс Метрики: до загрузки tag.js вызовы копятся в a, l — время создания очереди. */
export type YandexMetrika = ((...args: unknown[]) => void) & { a: unknown[][], l: number }

/** Функция пикселя Meta: до загрузки fbevents.js вызовы копятся в queue. */
export type MetaPixel = ((...args: unknown[]) => void) & {
  callMethod?: (...args: unknown[]) => void
  queue: unknown[][]
  push?: MetaPixel
  loaded: boolean
  version: string
}
