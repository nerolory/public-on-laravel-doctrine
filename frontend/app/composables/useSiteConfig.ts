import type { RichEditorLimits } from '~~/editor/rich-editor'
import type { Counter } from '~/metrics'

/** Лимиты форм: настройки публикаций и длины полей, которые проверяет сервер. */
type FormLimits = RichEditorLimits & {
  article_title: number
  summary: number
  tags: number
  tag_length: number
  poll_question: number
  poll_options: number
  poll_option_length: number
  album_title: number
  album_photos: number
  comment_length: number
  complaint_text: number
  reason: number
  person_name: number
  email: number
  social_url: number
  phone: number
  about: number
  password: number
}

/** Причина жалобы из /api/site: значение для отправки и признак обязательного пояснения. */
type ComplaintReasonOption = { value: string, requires_text: boolean }

/** Публичные настройки сайта из /api/site: причины жалоб приходят списком в порядке показа. */
export type SiteConfig = { counters: Counter[], limits: FormLimits, complaint_reasons: ComplaintReasonOption[] }

/** Настройки сайта, загруженные плагином site-config до отрисовки страниц. */
export function useSiteConfig(): SiteConfig {
  return useNuxtApp().$siteConfig
}

/** Лимиты форм из настроек сайта. */
export function useFormLimits(): FormLimits {
  return useSiteConfig().limits
}
