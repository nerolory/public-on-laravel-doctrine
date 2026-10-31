/**
 * Редактор статьи на TipTap 3 без привязки к фреймворку. Подключается в Nuxt (компонент RichEditor.client.vue)
 * и в админке MoonShine (точка входа article-editor.ts).
 * Сервер принимает только разрешённый HTML: картинки уходят ссылками data-image,
 * графики и альбомы — блоками data-chart / data-album.
 */
import { Editor } from '@tiptap/core'
import { TableKit } from '@tiptap/extension-table'
import StarterKit from '@tiptap/starter-kit'
import { focusedChain } from './commands'
import { labelText } from '../../shared/utils/labels'
import { createChartStore } from './chart-store'
import { el } from './dom'
import { insertDialogs } from './insert-dialogs'
import { AlbumNode, ArticleImage, createChartNode } from './nodes'
import { createToolbars } from './toolbar'
import type { Label, RichEditorLabels, RichEditorOptions } from './types'

export type * from './types'

/** Атрибуты ссылок в тексте: без передачи адреса страницы и без веса для поисковиков. */
const LINK_ATTRIBUTES = { rel: 'noopener noreferrer nofollow ugc', target: null }

/** Подпись по ключу с подстановкой значений; ключа нет в переводах или не передано значение для {name} — ошибка. */
function labeller(labels: RichEditorLabels): Label {
  return (key, values = {}) => labelText(labels, key).replace(/\{(\w+)\}/g, (_, name: string) => {
    const value = values[name]

    if (value === undefined) {
      throw new Error(`В подписи «${key}» нет значения для подстановки {${name}}`)
    }

    return String(value)
  })
}

/** Создаёт редактор внутри options.element. */
export function createRichEditor(options: RichEditorOptions) {
  const label = labeller(options.labels)
  const charts = createChartStore(options.api)
  const naming: Record<string, string> = options.labelledBy
    ? { 'aria-labelledby': options.labelledBy }
    : { 'aria-label': label('content') }
  const surface = el('div', { class: 'rich-editor-surface' })
  const status = el('p', { 'class': 'rich-editor-status', 'role': 'status', 'aria-live': 'polite' })
  const editor = new Editor({
    element: surface,
    content: options.content,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
        link: { openOnClick: false, autolink: true, protocols: ['mailto'], HTMLAttributes: LINK_ATTRIBUTES },
      }),
      ArticleImage,
      createChartNode(charts, label),
      AlbumNode,
      TableKit.configure({ table: { resizable: false } }),
    ],
    editorProps: {
      attributes: { 'class': 'rich-editor-content', 'role': 'textbox', 'aria-multiline': 'true', ...naming },
    },
    onUpdate: ({ editor: current }) => options.onChange(current.getHTML()),
  })
  const chain = () => focusedChain(editor)
  const dialogs = insertDialogs({
    editor,
    chain,
    label,
    api: options.api,
    limits: options.limits,
    charts,
    errorText: options.errorText,
    say: (message) => {
      status.textContent = message
    },
  })
  const toolbars = createToolbars(editor, chain, label, dialogs)

  // Панель подписывается на изменения после создания: состояние кнопок зависит от выделения и истории.
  editor.on('transaction', toolbars.refresh)
  const root = el('div', { class: 'rich-editor' }, [...toolbars.elements, surface, status])

  options.element.append(root)
  toolbars.refresh()

  return {
    editor,
    setContent(html: string) {
      if (html !== editor.getHTML()) {
        editor.commands.setContent(html, { emitUpdate: false })
      }
    },
    destroy() {
      editor.destroy()
      root.remove()
    },
  }
}
