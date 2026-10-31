/**
 * Редактор статьи на странице формы в админке MoonShine: тот же модуль, что и на сайте.
 * Включается на textarea[data-rich-editor]; обложка — на скрытом поле input[data-cover-field].
 */
import { apiErrorText } from '../shared/utils/api-errors'
import { createRichEditor } from './rich-editor'
import { createEditorApi } from './rich-editor/api'
import { mountCoverField } from './admin/cover-field'
import { parseLabels, parseLimits } from './admin/data-attributes'
import { request } from './admin/request'
import './rich-editor/rich-editor.scss'

/** Отправка запросов редактора из админки: сессия администратора и CSRF-токен страницы. */
const api = createEditorApi({
  get: path => request(path, 'GET'),
  post: (path, body) => request(path, 'POST', body),
})

/** Редактор поверх textarea: исходное поле скрыто, остаётся в форме и получает HTML. */
function mountEditor(textarea: HTMLTextAreaElement): void {
  const host = document.createElement('div')

  // Скрытое поле не участвует в проверке required.
  textarea.hidden = true
  textarea.required = false
  textarea.after(host)
  createRichEditor({
    element: host,
    content: textarea.value,
    labels: parseLabels(textarea.dataset.labels),
    limits: parseLimits(textarea.dataset.limits),
    errorText: apiErrorText,
    onChange: (html) => {
      textarea.value = html
    },
    api,
  })
}

document.querySelectorAll<HTMLTextAreaElement>('textarea[data-rich-editor]').forEach(mountEditor)
document.querySelectorAll<HTMLInputElement>('input[data-cover-field]').forEach(input => mountCoverField(input, api))
