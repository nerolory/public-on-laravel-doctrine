/** Элемент с атрибутами и дочерними узлами; тип элемента следует из имени тега. */
export function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  attributes: Record<string, string> = {},
  children: (Node | string)[] = [],
): HTMLElementTagNameMap[K] {
  const element = document.createElement(tag)

  for (const [name, value] of Object.entries(attributes)) {
    element.setAttribute(name, value)
  }

  element.append(...children)

  return element
}

/** Поле ввода: true ставит атрибут без значения, false его пропускает. */
export function input(attributes: Record<string, string | number | boolean>): HTMLInputElement {
  const element = document.createElement('input')

  for (const [name, value] of Object.entries(attributes)) {
    if (value === true) {
      element.setAttribute(name, '')
    }

    if (typeof value === 'string' || typeof value === 'number') {
      element.setAttribute(name, String(value))
    }
  }

  if (typeof attributes.value === 'string') {
    element.value = attributes.value
  }

  return element
}

/** Список выбора с вариантами и выбранным значением. */
export function select(
  options: { value: string, text: string }[],
  value: string,
  attributes: Record<string, string> = {},
): HTMLSelectElement {
  const element = el('select', attributes)

  for (const option of options) {
    element.append(el('option', { value: option.value }, [option.text]))
  }

  element.value = value

  return element
}

/** Поле формы с подписью. */
export function field(text: string, control: HTMLElement): HTMLElement {
  return el('label', { class: 'rich-editor-field' }, [el('span', {}, [text]), control])
}

/** Кнопка-крестик удаления строки или столбца. */
export function removeButton(text: string, run: () => void): HTMLButtonElement {
  const button = el(
    'button',
    { 'type': 'button', 'class': 'rich-editor-remove', 'aria-label': text, 'title': text },
    [el('span', { 'aria-hidden': 'true' }, ['×'])],
  )

  button.addEventListener('click', run)

  return button
}

/** Текстовая кнопка с обработчиком нажатия. */
export function textButton(text: string, run: () => void): HTMLButtonElement {
  const button = el('button', { type: 'button', class: 'rich-editor-text-button' }, [text])

  button.addEventListener('click', run)

  return button
}
