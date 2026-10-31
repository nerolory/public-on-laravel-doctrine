import { apiErrorText } from '../../shared/utils/api-errors'
import { IMAGE_TYPES, selectedFiles } from '../../shared/utils/images'
import { labelText } from '../../shared/utils/labels'
import type { RichEditorApi } from '../rich-editor'
import { el } from '../rich-editor/dom'
import { parseLabels } from './data-attributes'

/** Выбор обложки статьи: превью, загрузка и удаление. В скрытое поле пишется UUID картинки. */
export function mountCoverField(input: HTMLInputElement, api: RichEditorApi): void {
  const labels = parseLabels(input.dataset.labels)
  const label = (key: string) => labelText(labels, key)
  const preview = el('img', { alt: '' })
  const file = el('input', { type: 'file', accept: IMAGE_TYPES, class: 'rich-editor-visually-hidden' })
  const caption = document.createTextNode('')
  const choose = el('label', { class: 'rich-editor-text-button' }, [caption, file])
  const remove = el('button', { type: 'button', class: 'rich-editor-text-button' }, [label('cover_remove')])
  const status = el('p', { class: 'rich-editor-error', role: 'alert' })

  const render = (url: string | null) => {
    preview.hidden = !url

    if (url) {
      preview.src = url
    }

    remove.hidden = !input.value
    caption.textContent = label(input.value ? 'cover_replace' : 'cover_choose')
  }

  file.addEventListener('change', async () => {
    const chosen = selectedFiles(file)[0]

    if (!chosen) {
      return
    }

    status.textContent = ''

    try {
      const image = await api.uploadImage(chosen, 'cover')

      input.value = image.id
      render(image.url)
    } catch (error) {
      status.textContent = apiErrorText(error, label('upload_failed'))
    } finally {
      file.value = ''
    }
  })
  remove.addEventListener('click', () => {
    input.value = ''
    render(null)
  })
  input.after(el('div', { class: 'rich-editor-cover' }, [preview, choose, remove, status]))
  render(input.dataset.url || null)
}
