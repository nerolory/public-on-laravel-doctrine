import type { Editor } from '@tiptap/core'
import type { AlbumLayout, ImageView, PublicationStatus } from '../../shared/types/content'
import type { ImagePurpose } from '../../shared/utils/images'
import type { focusedChain } from './commands'
import type { ChartStore } from './chart-store'

/** Загруженная картинка: идентификатор и данные для показа. */
export type UploadedImage = ImageView & { id: string }

export type ChartType = 'bar' | 'line' | 'pie'

/** Таблица графика: строки — подписи, столбцы — ряды. */
export type ChartPayload = {
  type: ChartType
  title: string
  labels: string[]
  series: { name: string, values: number[] }[]
}

/** Сохранённый график: UUID и таблица данных. */
export type ChartResult = ChartPayload & { id: string }

/** Альбом автора для вставки в статью. */
export type AlbumSummary = { id: string, title: string, status: PublicationStatus }

/** Атрибуты блока альбома в тексте статьи. */
export type AlbumAttributes = { album: string, layout: AlbumLayout, title: string }

/** Запросы редактора к серверу. */
export type RichEditorApi = {
  uploadImage: (file: File, purpose: ImagePurpose) => Promise<UploadedImage>
  createChart: (data: ChartPayload) => Promise<ChartResult>
  loadChart: (id: string) => Promise<ChartResult>
  /** Свои альбомы для вставки; на сайте показываются только опубликованные. */
  listAlbums: () => Promise<AlbumSummary[]>
}

/** Подписи из lang/{язык}/editor.php; подстановки вида {max} заменяет сам редактор. */
export type RichEditorLabels = Record<string, string>

/** Лимиты из настроек сайта (/api/site): число картинок, размеры таблицы графика и длины полей. */
export type RichEditorLimits = {
  images: number
  chart_labels: number
  chart_series: number
  chart_title: number
  chart_label: number
  image_alt: number
}

/** Параметры создания редактора. */
export type RichEditorOptions = {
  element: HTMLElement
  content: string
  labels: RichEditorLabels
  api: RichEditorApi
  limits: RichEditorLimits
  onChange: (html: string) => void
  /** Текст ошибки из ответа сервера; без ответа — переданный текст. */
  errorText: (error: unknown, fallback: string) => string
  /** Id подписи поля на странице; без неё текст называется подписью content из переводов. */
  labelledBy?: string
}

/** Подпись по ключу с подстановкой значений вида {max}. */
export type Label = (key: string, values?: Record<string, string | number>) => string

/** Общее состояние редактора для панели инструментов и диалогов. */
export type EditorContext = {
  editor: Editor
  /** Цепочка команд с возвратом фокуса в текст. */
  chain: () => ReturnType<typeof focusedChain>
  label: Label
  api: RichEditorApi
  limits: RichEditorLimits
  /** Данные графиков редактора. */
  charts: ChartStore
  errorText: (error: unknown, fallback: string) => string
  /** Сообщение в строке состояния под редактором. */
  say: (message: string) => void
}

/** Узел TipTap для вставки блоком. */
export type BlockNode = { type: string, attrs: Record<string, unknown> }
