<script setup lang="ts">
import type { DialogControls } from '~/composables/useDialog'

/**
 * Окно с формой: заголовок, поля слотом, сообщение о результате и кнопки отправки и отмены.
 * Открывается и закрывается родителем через ref; отправка уходит событием submit.
 */
defineProps<{ title: string, submitText: string, submitDisabled: boolean, error: string }>()
const emit = defineEmits<{ submit: [] }>()
const dialog = ref<DialogControls | null>(null)

function open() {
  dialog.value?.open()
}

function close() {
  dialog.value?.close()
}

defineExpose<DialogControls>({ open, close })
</script>

<template>
  <AppDialog
    ref="dialog"
    v-slot="{ titleId }"
  >
    <form
      class="dialog-form"
      @submit.prevent="emit('submit')"
    >
      <h2 :id="titleId">
        {{ title }}
      </h2>
      <slot />
      <FormMessage
        v-if="error"
        :text="error"
        tone="error"
      />
      <div class="dialog-actions">
        <button
          type="submit"
          class="danger-button"
          :disabled="submitDisabled"
        >
          {{ submitText }}
        </button>
        <button
          type="button"
          class="text-button"
          @click="close()"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </form>
  </AppDialog>
</template>
