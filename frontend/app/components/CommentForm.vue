<script setup lang="ts">
/** Поле комментария с кнопкой отправки: новый комментарий, правка и ответ; у правки и ответа есть отмена. */
withDefaults(defineProps<{
  id: string
  label: string
  submitText: string
  busy: boolean
  rows: number
  placeholder?: string
  error?: string
  cancelable?: boolean
}>(), { placeholder: undefined, error: '', cancelable: false })
defineEmits<{ submit: [], cancel: [] }>()

const draft = defineModel<string>({ required: true })
const limits = useFormLimits()
</script>

<template>
  <form
    class="comment-form"
    @submit.prevent="$emit('submit')"
  >
    <label
      class="visually-hidden"
      :for="id"
    >{{ label }}</label>
    <textarea
      :id="id"
      v-model="draft"
      required
      :maxlength="limits.comment_length"
      :rows="rows"
      class="article-input"
      :placeholder="placeholder"
    />
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
    <div class="comment-form-actions">
      <button
        type="submit"
        class="primary-button comment-submit"
        :disabled="busy || !draft.trim()"
      >
        {{ submitText }}
      </button><button
        v-if="cancelable"
        type="button"
        class="text-button"
        @click="$emit('cancel')"
      >
        {{ $t('common.cancel') }}
      </button>
    </div>
  </form>
</template>
