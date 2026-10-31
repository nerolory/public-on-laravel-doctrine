/**
 * Счётчики (Яндекс Метрика, GA4, GTM, пиксели VK и Meta) из настроек сайта. Скрипт счётчика загружается
 * только после согласия на его категорию cookies: аналитика или реклама. Где баннер не нужен по закону (США),
 * согласие считается данным. Цели: «registration» и «article_view».
 */
import { CONSENT_COOKIE, drivers } from '~/metrics'
import type { Consent, Counter, Goal } from '~/metrics'

export default defineNuxtPlugin({
  name: 'metrics',
  dependsOn: ['site-config', 'legal'],
  setup(nuxtApp) {
    const consent = useCookie<Consent | null>(CONSENT_COOKIE)
    const terms = useLegal()
    const available = useSiteConfig().counters
    const active: Counter[] = []
    /** Выбор из баннера: cookie к этому моменту может быть ещё не перечитана. */
    let choice: Consent | null = null

    /** Счётчик разрешён, если баннер в регионе не нужен или посетитель согласился на его категорию cookies. */
    const allowed = (counter: Counter) => {
      const given = choice ?? consent.value

      return !terms.cookie_banner || given?.[counter.consent] === true
    }

    /** Подключает счётчики, на которые есть согласие; уже подключённые пропускает. */
    const apply = (next?: Consent) => {
      choice = next ?? choice

      for (const counter of available) {
        if (active.some(item => item.type === counter.type) || !allowed(counter)) {
          continue
        }

        drivers[counter.type].init(counter.id, counter.script)
        drivers[counter.type].page(counter.id)
        active.push(counter)
      }
    }

    const goal = (name: Goal) => active.forEach(counter => drivers[counter.type].goal(counter.id, name))

    nuxtApp.hook('app:mounted', () => apply())
    nuxtApp.hook('page:finish', () => active.forEach(counter => drivers[counter.type].page(counter.id)))

    return { provide: { metrics: { apply, goal } } }
  },
})
