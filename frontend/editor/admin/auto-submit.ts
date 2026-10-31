/** Автоотправка: смена значения селекта с атрибутом data-auto-submit отправляет его форму. */
export function mountAutoSubmit(): void {
  document.addEventListener('change', (event) => {
    const target = event.target

    if (target instanceof HTMLSelectElement && target.matches('[data-auto-submit]')) {
      target.form?.requestSubmit()
    }
  })
}
