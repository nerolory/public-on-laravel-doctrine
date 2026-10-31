import { ga4 } from './ga4'
import { gtm } from './gtm'
import { meta } from './meta'
import type { CounterDriver, CounterType } from './types'
import { vk } from './vk'
import { yandex } from './yandex'

export type * from './types'
export { CONSENT_COOKIE, CONSENT_MAX_AGE, CONSENT_VERSION } from './consent'

/** Подключение каждого вида счётчика. */
export const drivers: Record<CounterType, CounterDriver> = { yandex, ga4, gtm, vk, meta }
