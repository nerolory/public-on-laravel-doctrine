import { ensureWindowGlobal } from './globals'
import { loadScript } from './script'
import type { CounterDriver, YandexMetrika } from './types'

/** Параметры счётчика: карта кликов, внешние ссылки, точный показатель отказов, отложенная загрузка. */
const INIT_OPTIONS = { clickmap: true, trackLinks: true, accurateTrackBounce: true, defer: true }

const metrika = () => ensureWindowGlobal('ym', () => {
  const stub: YandexMetrika = Object.assign((...args: unknown[]) => {
    stub.a.push(args)
  }, { a: [], l: Date.now() })

  return stub
})

/** Яндекс Метрика. */
export const yandex: CounterDriver = {
  init(id, src) {
    const ym = metrika()

    loadScript(src)
    ym(Number(id), 'init', INIT_OPTIONS)
  },
  page: id => metrika()(Number(id), 'hit', location.href, { title: document.title }),
  goal: (id, goal) => metrika()(Number(id), 'reachGoal', goal),
}
