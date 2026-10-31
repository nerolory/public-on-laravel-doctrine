import type { Editor } from '@tiptap/core'

/** Цепочка команд редактора с возвратом фокуса в текст: так работают кнопки панели и окна вставки. */
export function focusedChain(editor: Editor) {
  return editor.chain().focus()
}
