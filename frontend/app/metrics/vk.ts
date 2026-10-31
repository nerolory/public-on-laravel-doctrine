import { ensureWindowGlobal } from './globals'
import { loadScript } from './script'
import type { CounterDriver } from './types'

/** Очередь событий пикселя VK (top.mail.ru). */
const queue = () => ensureWindowGlobal('_tmr', () => [])

/** Пиксель VK Рекламы. */
export const vk: CounterDriver = {
  init(_, src) {
    queue()
    loadScript(src)
  },
  page: id => queue().push({ id, type: 'pageView', start: Date.now() }),
  goal: (id, goal) => queue().push({ id, type: 'reachGoal', goal }),
}
