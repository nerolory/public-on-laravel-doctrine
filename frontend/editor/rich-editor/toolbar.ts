import type { Editor } from '@tiptap/core'
import { el } from './dom'
import type { EditorContext, Label } from './types'

/** Кнопка панели: ключ подписи, значок, действие и состояние. */
type ButtonSpec = { key: string, icon: string, run: () => void, active?: () => boolean, enabled?: () => boolean }

/** Действия, которые открывают окна вставки. */
type ToolbarDialogs = { link: () => void, image: () => void, chart: () => void, album: () => void }

/** Размер новой таблицы: строки и столбцы. */
const NEW_TABLE = { rows: 3, cols: 3, withHeaderRow: true }

/** Основная панель и панель работы с таблицей; refresh обновляет нажатые и недоступные кнопки. */
export function createToolbars(editor: Editor, chain: EditorContext['chain'], label: Label, dialogs: ToolbarDialogs) {
  const main = el('div', { 'class': 'rich-editor-toolbar', 'role': 'toolbar', 'aria-label': label('toolbar') })
  const table = el('div', {
    'class': 'rich-editor-toolbar rich-editor-table',
    'role': 'toolbar',
    'aria-label': label('table_tools'),
    'hidden': '',
  })
  const mainButtons: ButtonSpec[] = [
    { key: 'bold', icon: 'B', run: () => chain().toggleBold().run(), active: () => editor.isActive('bold') },
    { key: 'italic', icon: 'I', run: () => chain().toggleItalic().run(), active: () => editor.isActive('italic') },
    {
      key: 'underline',
      icon: 'U',
      run: () => chain().toggleUnderline().run(),
      active: () => editor.isActive('underline'),
    },
    { key: 'strike', icon: 'S', run: () => chain().toggleStrike().run(), active: () => editor.isActive('strike') },
    {
      key: 'heading2',
      icon: 'H2',
      run: () => chain().toggleHeading({ level: 2 }).run(),
      active: () => editor.isActive('heading', { level: 2 }),
    },
    {
      key: 'heading3',
      icon: 'H3',
      run: () => chain().toggleHeading({ level: 3 }).run(),
      active: () => editor.isActive('heading', { level: 3 }),
    },
    {
      key: 'bullet_list',
      icon: '•',
      run: () => chain().toggleBulletList().run(),
      active: () => editor.isActive('bulletList'),
    },
    {
      key: 'ordered_list',
      icon: '1.',
      run: () => chain().toggleOrderedList().run(),
      active: () => editor.isActive('orderedList'),
    },
    {
      key: 'blockquote',
      icon: '“',
      run: () => chain().toggleBlockquote().run(),
      active: () => editor.isActive('blockquote'),
    },
    {
      key: 'code_block',
      icon: '</>',
      run: () => chain().toggleCodeBlock().run(),
      active: () => editor.isActive('codeBlock'),
    },
    { key: 'link', icon: '🔗', run: dialogs.link, active: () => editor.isActive('link') },
    { key: 'image', icon: '🖼', run: dialogs.image },
    {
      key: 'table',
      icon: '▦',
      run: () => chain().insertTable(NEW_TABLE).run(),
      enabled: () => !editor.isActive('table'),
    },
    { key: 'chart', icon: '📊', run: dialogs.chart, active: () => editor.isActive('chart') },
    { key: 'album', icon: '🗂', run: dialogs.album, active: () => editor.isActive('album') },
    { key: 'undo', icon: '↶', run: () => chain().undo().run(), enabled: () => editor.can().undo() },
    { key: 'redo', icon: '↷', run: () => chain().redo().run(), enabled: () => editor.can().redo() },
  ]
  const tableButtons: ButtonSpec[] = [
    { key: 'add_row', icon: '+⎯', run: () => chain().addRowAfter().run() },
    { key: 'add_column', icon: '+|', run: () => chain().addColumnAfter().run() },
    { key: 'delete_row', icon: '−⎯', run: () => chain().deleteRow().run() },
    { key: 'delete_column', icon: '−|', run: () => chain().deleteColumn().run() },
    { key: 'toggle_header', icon: 'Th', run: () => chain().toggleHeaderRow().run() },
    { key: 'delete_table', icon: '✕', run: () => chain().deleteTable().run() },
  ]
  const rendered = new Map<ButtonSpec, HTMLButtonElement>([
    ...mainButtons.map(spec => [spec, render(spec, main, label)] as const),
    ...tableButtons.map(spec => [spec, render(spec, table, label)] as const),
  ])

  return {
    elements: [main, table],
    refresh() {
      for (const [spec, button] of rendered) {
        if (spec.active) {
          button.setAttribute('aria-pressed', String(spec.active()))
        }

        button.disabled = spec.enabled ? !spec.enabled() : false
      }

      table.hidden = !editor.isActive('table')
    },
  }
}

/** Кнопка панели с подписью для экранных дикторов; значок скрыт от них. */
function render(spec: ButtonSpec, bar: HTMLElement, label: Label): HTMLButtonElement {
  const text = label(spec.key)
  const button = el(
    'button',
    { 'type': 'button', 'class': 'rich-editor-button', 'aria-label': text, 'title': text },
    [el('span', { 'aria-hidden': 'true' }, [spec.icon])],
  )

  if (spec.active) {
    button.setAttribute('aria-pressed', 'false')
  }

  button.addEventListener('click', () => spec.run())
  bar.append(button)

  return button
}
