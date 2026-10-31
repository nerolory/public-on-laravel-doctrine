import { el, textButton } from './dom'

/** Параметры модального окна редактора. */
export type DialogOptions = {
  title: string
  fields: HTMLElement[]
  submit: string
  cancel: string
  wide?: boolean
  /** Дополнительные кнопки: выполняют действие и закрывают окно. */
  extra?: { text: string, run: () => void }[]
  /** Возвращает текст ошибки, если окно нужно оставить открытым. */
  onSubmit: () => Promise<string | undefined>
  onClose: () => void
}

/** Порядковый номер окна: из него собирается id заголовка для aria-labelledby. */
let opened = 0

/** Модальное окно на нативном dialog: фокус, Esc и затемнение даёт браузер. */
export function openDialog(options: DialogOptions): void {
  const dialog = el('dialog', {
    class: options.wide ? 'rich-editor-dialog rich-editor-dialog-wide' : 'rich-editor-dialog',
  })
  const heading = el('h2', { id: `rich-editor-dialog-${++opened}` }, [options.title])
  const form = el('form', { class: 'rich-editor-dialog-form' })
  const error = el('p', { class: 'rich-editor-error', role: 'alert' })
  const submit = el('button', { type: 'submit', class: 'rich-editor-primary' }, [options.submit])
  const extra = (options.extra ?? []).map(action => textButton(action.text, () => {
    action.run()
    dialog.close()
  }))
  const actions = el(
    'div',
    { class: 'rich-editor-dialog-actions' },
    [submit, ...extra, textButton(options.cancel, () => dialog.close())],
  )

  dialog.setAttribute('aria-labelledby', heading.id)
  form.append(heading, ...options.fields, error, actions)
  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    submit.disabled = true
    error.textContent = ''

    try {
      const problem = await options.onSubmit()

      if (problem) {
        error.textContent = problem

        return
      }

      dialog.close()
    } finally {
      submit.disabled = false
    }
  })
  dialog.addEventListener('close', () => {
    dialog.remove()
    options.onClose()
  })
  dialog.append(form)
  document.body.append(dialog)
  dialog.showModal()
}
