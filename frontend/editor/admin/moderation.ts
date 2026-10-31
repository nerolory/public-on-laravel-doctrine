/**
 * Модерация в списках админки (статьи, альбомы). Отклонение требует причину: её спрашивает диалог #reject-dialog,
 * затем она уходит в скрытое поле reason формы. Разметка подключается атрибутами:
 * data-moderation-status — список статуса в строке, data-moderation-bulk — форма массовых действий,
 * data-confirm — кнопка или ссылка с подтверждением.
 */

/** Форма, ожидающая причину отклонения, и действие при отмене. */
type PendingReason = { form: HTMLFormElement, onCancel?: () => void }

let pending: PendingReason | null = null

const dialog = () => document.querySelector<HTMLDialogElement>('#reject-dialog')
const field = () => document.querySelector<HTMLTextAreaElement>('#reject-reason')

/** Диалог причины и его поле: на странице без них отклонение невозможно, это ошибка разметки. */
function reasonDialog(): { modal: HTMLDialogElement, reasonField: HTMLTextAreaElement } {
  const modal = dialog()
  const reasonField = field()

  if (!modal || !reasonField) {
    throw new Error('На странице нет диалога #reject-dialog или поля причины #reject-reason')
  }

  return { modal, reasonField }
}

/** Открывает диалог причины и запоминает форму, которую он подтвердит. */
function askReason(form: HTMLFormElement, onCancel?: () => void): void {
  const { modal, reasonField } = reasonDialog()

  pending = { form, onCancel }
  reasonField.value = ''
  modal.returnValue = ''
  modal.showModal()
}

/** Закрытие диалога: подтверждённая причина записывается в форму и форма отправляется. */
function reasonClosed(): void {
  const current = pending

  pending = null

  if (!current) {
    return
  }

  const { modal, reasonField } = reasonDialog()
  const reason = reasonField.value.trim()

  if (modal.returnValue !== 'confirm' || reason === '') {
    current.onCancel?.()

    return
  }

  const input = current.form.elements.namedItem('reason')

  if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
    throw new Error('В форме модерации нет поля причины reason')
  }

  input.value = reason

  current.form.dataset.reasonReady = '1'
  current.form.requestSubmit()
}

/** Смена статуса в строке списка: отклонение сначала спрашивает причину. */
function statusChanged(select: HTMLSelectElement): void {
  if (!select.form) {
    return
  }

  if (select.value !== 'reject') {
    select.form.requestSubmit()

    return
  }

  const previous = select.dataset.current

  if (previous === undefined) {
    throw new Error('У списка статуса нет атрибута data-current')
  }

  askReason(select.form, () => {
    select.value = previous
  })
}

/** Текст подтверждения из атрибута; без атрибута действие отменяется и ошибка разметки видна в консоли. */
function confirmText(event: Event, value: string | undefined, attribute: string): string {
  if (value === undefined) {
    event.preventDefault()

    throw new Error(`Не задан атрибут ${attribute} у действия с подтверждением`)
  }

  return value
}

/** Массовое действие: удаление подтверждается, отклонение спрашивает причину. */
function bulkSubmitted(event: SubmitEvent, form: HTMLFormElement): void {
  const action = form.elements.namedItem('action')
  const value = action instanceof HTMLSelectElement || action instanceof HTMLInputElement ? action.value : ''

  if (value === 'delete' && !confirm(confirmText(event, form.dataset.confirmDelete, 'data-confirm-delete'))) {
    event.preventDefault()

    return
  }

  if (value === 'reject' && !form.dataset.reasonReady) {
    event.preventDefault()
    askReason(form)

    return
  }

  delete form.dataset.reasonReady
}

/** Подключает обработчики модерации к документу. */
export function mountModeration(): void {
  dialog()?.addEventListener('close', reasonClosed)

  document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLSelectElement && event.target.matches('[data-moderation-status]')) {
      statusChanged(event.target)
    }
  })

  document.addEventListener('submit', (event) => {
    if (event.target instanceof HTMLFormElement && event.target.matches('[data-moderation-bulk]')) {
      bulkSubmitted(event, event.target)
    }
  })

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest<HTMLElement>('[data-confirm]') : null

    if (target && !confirm(confirmText(event, target.dataset.confirm, 'data-confirm'))) {
      event.preventDefault()
    }
  })
}
