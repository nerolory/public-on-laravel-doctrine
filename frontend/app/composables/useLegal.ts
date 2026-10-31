/** Документ для регистрации: тип согласия, название, текст и обязательность. */
export type LegalDocument = { type: string, title: string, text: string, required: boolean }

/** Согласие пользователя на документ: обязательность, принятие, актуальность и можно ли его отозвать в настройках. */
export type ConsentStatus = {
  type: string
  title: string
  required: boolean
  agreed: boolean
  current: boolean
  revocable: boolean
}

/** Условия правового региона: документы для регистрации, необходимость баннера cookies и выгрузки данных. */
export type LegalTerms = { cookie_banner: boolean, documents: LegalDocument[] }

/** Категории cookies, на которые спрашивается согласие: аналитика и реклама. */
export const COOKIE_CATEGORIES = ['analytics', 'marketing'] as const

type CookieCategory = typeof COOKIE_CATEGORIES[number]

/** Тип согласия в журнале для каждой категории cookies. */
export const COOKIE_CONSENTS: Record<CookieCategory, string> = {
  analytics: 'analytics_cookies',
  marketing: 'marketing_cookies',
}

/** Согласие на категорию cookies, которое не показывается среди документов регистрации. */
export function isCookieConsent(type: string): boolean {
  return Object.values(COOKIE_CONSENTS).includes(type)
}

/** Условия правового региона из настроек сайта, загруженные плагином legal до отрисовки страниц. */
export function useLegal(): LegalTerms {
  return useNuxtApp().$legalTerms
}
