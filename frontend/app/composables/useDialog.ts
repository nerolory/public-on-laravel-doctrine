/** Модальное окно на нативном dialog: открытие и закрытие. */
export function useDialog() {
  const dialog = ref<HTMLDialogElement | null>(null)

  function open() {
    dialog.value?.showModal()
  }

  function close() {
    dialog.value?.close()
  }

  return { dialog, open, close }
}

/** Компонент с окном, которое открывает родитель. */
export type DialogHandle = { open: () => void }

/** Окно, которым управляет родитель через ref: открыть и закрыть. */
export type DialogControls = { open: () => void, close: () => void }

/** Окно формы: при каждом открытии сбрасываются поля и сообщение о результате через reset, затем окно открывается. */
export function useFormDialog(reset: () => void) {
  const dialog = ref<DialogControls | null>(null)

  function open() {
    reset()
    dialog.value?.open()
  }

  function close() {
    dialog.value?.close()
  }

  return { dialog, open, close }
}
