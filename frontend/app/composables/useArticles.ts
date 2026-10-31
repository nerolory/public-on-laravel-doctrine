import type { UploadedImage } from '~~/editor/rich-editor'
import type { PublicationStatus } from '#shared/types/content'

export type ArticleStatus = PublicationStatus

/** Статья в списках ленты и карточки автора. */
export type ArticleListItem = { id: number, title: string, excerpt: string, sections: string[] }

/** Раздел для выбора в форме статьи: уровень вложенности задаёт отступ. */
export type SectionOption = { id: number, name: string, level: number }

/** Фото альбома; alt равен null, если описания нет. */
export type AlbumItem = { image: UploadedImage, alt: string | null }

/** Альбом — как его отдаёт сервер владельцу. */
export type AlbumView = {
  id: string
  title: string
  status: ArticleStatus
  rejection_reason: string | null
  items: AlbumItem[]
}

/** Опрос в форме статьи. ends_at — значение поля datetime-local в часовом поясе посетителя. */
export type PollForm = {
  question: string
  options: string[]
  multiple: boolean
  max_choices: number | null
  ends_at: string
  allow_revote: boolean
  anonymous: boolean
  locked?: boolean
}

/** Опрос в ответе формы правки: дата окончания в ISO. */
export type PollFormData = Omit<PollForm, 'ends_at'> & { ends_at: string | null }

/** Опрос на странице статьи — как его отдаёт сервер. */
export type PollVoter = { id: number, name: string, avatar: string | null }
export type PollOptionView = {
  id: number
  text: string
  votes: number
  percent: number
  voters: PollVoter[]
  more: number
}
export type PollViewData = {
  id: string
  question: string
  multiple: boolean
  max_choices: number
  ends_at: string | null
  closed: boolean
  allow_revote: boolean
  anonymous: boolean
  total_voters: number
  options: PollOptionView[]
  my_choice: number[] | null
  can_vote: boolean
}

/** Поля формы статьи — общие для новой статьи и правки. */
export type ArticleFormData = {
  title: string
  content: string
  summary: string
  section_ids: number[]
  tags: string[]
  cover: UploadedImage | null
  poll: PollForm | null
}

/** Наименьшее число вариантов ответа в опросе: с одним вариантом выбирать не из чего. */
export const POLL_MIN_OPTIONS = 2

/** Миллисекунд в минуте: смещение часового пояса в Date задаётся в минутах. */
const MS_PER_MINUTE = 60_000

/** Длина значения поля datetime-local: дата и время до минут (2026-10-30T12:00). */
const DATETIME_LOCAL_LENGTH = 16

export function emptyArticleForm(): ArticleFormData {
  return { title: '', content: '', summary: '', section_ids: [], tags: [], cover: null, poll: null }
}

export function emptyPoll(): PollForm {
  return {
    question: '',
    options: Array.from({ length: POLL_MIN_OPTIONS }, () => ''),
    multiple: false,
    max_choices: null,
    ends_at: '',
    allow_revote: false,
    anonymous: true,
  }
}

/** Опрос из ответа сервера для формы правки: дату ISO переводим в значение datetime-local. */
export function pollForForm(poll: PollFormData | null): PollForm | null {
  if (!poll) {
    return null
  }

  return { ...poll, ends_at: poll.ends_at ? toDateTimeLocal(poll.ends_at) : '' }
}

/** Тело запроса: обложка уходит своим UUID, дата окончания опроса — в ISO с часовым поясом. */
export function articlePayload(form: ArticleFormData): Record<string, unknown> {
  return { ...form, cover: form.cover?.id ?? null, poll: form.poll ? pollPayload(form.poll) : null }
}

/** Опрос для запроса: признак locked остаётся в форме, лимит выбора — только у опроса с несколькими ответами. */
function pollPayload(poll: PollForm): Record<string, unknown> {
  return {
    question: poll.question,
    options: poll.options,
    multiple: poll.multiple,
    max_choices: poll.multiple ? poll.max_choices : null,
    ends_at: poll.ends_at ? new Date(poll.ends_at).toISOString() : null,
    allow_revote: poll.allow_revote,
    anonymous: poll.anonymous,
  }
}

/** Значение поля datetime-local в часовом поясе посетителя из даты ISO. */
function toDateTimeLocal(value: string): string {
  const local = new Date(new Date(value).getTime() - new Date().getTimezoneOffset() * MS_PER_MINUTE)

  return local.toISOString().slice(0, DATETIME_LOCAL_LENGTH)
}
