<script setup lang="ts">
/**
 * Модальное окно на нативном dialog. Содержимое и заголовок передаёт родитель:
 * слот получает titleId для aria-labelledby.
 * Открывается и закрывается методами open и close. С persistent Esc не закрывает
 * окно: решает родитель по событию cancel.
 */
const props = defineProps<{ persistent?: boolean }>()
const emit = defineEmits<{ cancel: [] }>()
const { dialog, open, close } = useDialog()
const titleId = `${useId()}-title`

/** Esc в persistent-режиме не закрывает окно, событие уходит родителю. */
function onCancel(event: Event) {
  if (props.persistent) {
    event.preventDefault()
  }

  emit('cancel')
}

defineExpose({ open, close })
</script>

<template>
  <dialog
    ref="dialog"
    class="app-dialog"
    :aria-labelledby="titleId"
    @cancel="onCancel"
  >
    <slot :title-id="titleId" />
  </dialog>
</template>
