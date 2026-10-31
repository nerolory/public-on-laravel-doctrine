import { isRecord } from '../../shared/utils/guards'
import type { RichEditorLabels, RichEditorLimits } from '../rich-editor'

/** Объект JSON из data-атрибута; страница MoonShine всегда передаёт объект, без атрибута это ошибка разметки. */
function parseObject(value: string | undefined, attribute: string): Record<string, unknown> {
  if (value === undefined) {
    throw new Error(`Атрибут ${attribute} отсутствует на элементе редактора`)
  }

  const parsed: unknown = JSON.parse(value)

  if (!isRecord(parsed)) {
    throw new TypeError(`Атрибут ${attribute} должен содержать объект JSON`)
  }

  return parsed
}

/** Подписи редактора из data-labels: все значения — строки. */
export function parseLabels(value: string | undefined): RichEditorLabels {
  return Object.fromEntries(Object.entries(parseObject(value, 'data-labels')).map(([key, text]) => {
    if (typeof text !== 'string') {
      throw new TypeError(`Подпись data-labels.${key} должна быть строкой`)
    }

    return [key, text]
  }))
}

/** Лимиты редактора из data-limits: все нужные ключи — числа. */
export function parseLimits(value: string | undefined): RichEditorLimits {
  const limits = parseObject(value, 'data-limits')
  const number = (key: keyof RichEditorLimits): number => {
    const limit = limits[key]

    if (typeof limit !== 'number') {
      throw new TypeError(`Лимит data-limits.${key} должен быть числом`)
    }

    return limit
  }

  return {
    images: number('images'),
    chart_labels: number('chart_labels'),
    chart_series: number('chart_series'),
    chart_title: number('chart_title'),
    chart_label: number('chart_label'),
    image_alt: number('image_alt'),
  }
}
