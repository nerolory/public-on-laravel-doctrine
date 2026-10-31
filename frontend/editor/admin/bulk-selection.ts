/**
 * Массовый выбор строк в списках админки. Разметка: data-bulk-select-all — «выбрать все»,
 * data-bulk-item — строки, data-bulk-action — селекты действия, data-bulk-submit — кнопки отправки.
 * Все элементы связаны с ближайшей формой.
 */

const SELECT_ALL = '[data-bulk-select-all]'
const ITEM = '[data-bulk-item]'
const ACTION = '[data-bulk-action]'
const SUBMIT = '[data-bulk-submit]'
const CONTROLS = `${SELECT_ALL}, ${ITEM}, ${ACTION}`

/** Селекты действия, размеченные disabled: их доступность зависит от выбора строк. */
const rowGated = new WeakSet<HTMLSelectElement>()

const isInput = (element: Element): element is HTMLInputElement => element instanceof HTMLInputElement
const isSelect = (element: Element): element is HTMLSelectElement => element instanceof HTMLSelectElement
const isSubmit = (element: Element): element is HTMLButtonElement | HTMLInputElement =>
  element instanceof HTMLButtonElement || element instanceof HTMLInputElement

/** Элементы формы по селектору, отфильтрованные по типу. */
function within<T extends Element>(
  form: HTMLFormElement,
  selector: string,
  isType: (element: Element) => element is T,
): T[] {
  return Array.from(form.querySelectorAll(selector)).filter(isType)
}

/** Пересчитывает «выбрать все», селекты действия и кнопки отправки формы по выбранным строкам. */
function syncForm(form: HTMLFormElement): void {
  const rows = within(form, ITEM, isInput)
  const selected = rows.filter(row => row.checked).length

  for (const selectAll of within(form, SELECT_ALL, isInput)) {
    selectAll.checked = selected > 0 && selected === rows.length
    selectAll.indeterminate = selected > 0 && selected < rows.length
  }

  const actions = within(form, ACTION, isSelect)

  for (const action of actions) {
    if (rowGated.has(action)) {
      action.disabled = selected === 0
    }
  }

  const actionChosen = actions.some(action => action.value !== '')

  for (const submit of within(form, SUBMIT, isSubmit)) {
    submit.disabled = selected === 0 || !actionChosen
  }
}

/** Реакция на смену контрола: «выбрать все» отмечает строки, селект действия копируется в соседние. */
function changed(target: EventTarget | null): void {
  if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement) || !target.matches(CONTROLS)) {
    return
  }

  const form = target.closest('form')

  if (!form) {
    return
  }

  if (target instanceof HTMLInputElement && target.matches(SELECT_ALL)) {
    for (const row of within(form, ITEM, isInput)) {
      row.checked = target.checked
    }
  }

  if (target instanceof HTMLSelectElement && target.matches(ACTION)) {
    for (const action of within(form, ACTION, isSelect)) {
      action.value = target.value
    }
  }

  syncForm(form)
}

/** Подключает массовый выбор к документу и пересчитывает состояние всех форм страницы. */
export function mountBulkSelection(): void {
  for (const form of Array.from(document.forms)) {
    for (const action of within(form, ACTION, isSelect)) {
      if (action.disabled) {
        rowGated.add(action)
      }
    }

    syncForm(form)
  }

  document.addEventListener('change', (event) => {
    changed(event.target)
  })
}
