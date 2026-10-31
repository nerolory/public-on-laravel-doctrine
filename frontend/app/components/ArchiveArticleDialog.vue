<script setup lang="ts">
import type { DialogHandle } from '~/composables/useDialog'

/** Удаление статьи автором: статья уходит в архив, причину сервер требует обязательно. */
const props = defineProps<{ articleId: number, title: string }>()
const emit = defineEmits<{ archived: [] }>()
const { submit } = useAccount()
const { busy, error, run, reset } = useFormAction()
const limits = useFormLimits()
const reason = ref('')
const { dialog, open, close } = useFormDialog(() => {
  reason.value = ''
  reset()
})

async function archive() {
  const archived = await run(
    () => submit(apiPaths.myArticle(props.articleId), 'DELETE', { reason: reason.value }),
    { failure: 'archive.failed' },
  )

  if (archived) {
    close()
    emit('archived')
  }
}

defineExpose<DialogHandle>({ open })
</script>

<template>
  <DialogForm
    ref="dialog"
    :title="$t('archive.title')"
    :submit-text="$t('archive.submit')"
    :submit-disabled="busy"
    :error="error"
    @submit="archive"
  >
    <p>{{ $t('archive.intro', { title }) }}</p>
    <label>{{ $t('archive.reason') }}<textarea
      v-model="reason"
      required
      :maxlength="limits.reason"
      rows="4"
      class="article-input"
    /></label>
  </DialogForm>
</template>
