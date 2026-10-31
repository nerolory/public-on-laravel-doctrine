/** Вызывает обработчик при нажатии мыши вне элемента: закрытие выпадающих меню и панелей. */
export function useOutsideClick(target: Ref<HTMLElement | null>, handler: () => void) {
  const onPointer = (event: MouseEvent) => {
    if (event.target instanceof Node && !target.value?.contains(event.target)) {
      handler()
    }
  }

  onMounted(() => document.addEventListener('mousedown', onPointer))
  onBeforeUnmount(() => document.removeEventListener('mousedown', onPointer))
}
