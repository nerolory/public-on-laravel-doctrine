<script setup lang="ts">
/** «Пожаловаться» на статью, комментарий, пользователя или альбом. Жалобу разбирает модератор. */
const props = defineProps<{ target: 'article' | 'comment' | 'user' | 'album', id: string | number }>()
const { user, submit } = useAccount()
const { busy, error, run, reset } = useFormAction()
const limits = useFormLimits()
/** Причины жалобы из настроек сайта в порядке показа. */
const { complaint_reasons: reasons } = useSiteConfig()
const reason = ref('')
const text = ref('')
const done = ref('')
const uid = useId()
const { dialog, open, close } = useFormDialog(() => {
  reason.value = ''
  text.value = ''
  reset()
})
/** Пояснение обязательно, если выбранная причина помечена requires_text. */
const textRequired = computed(() => reasons.some(item => item.value === reason.value && item.requires_text))

async function send() {
  const body = { target: props.target, id: String(props.id), reason: reason.value, text: text.value || null }

  await run(async () => {
    done.value = (await submit<{ message: string }>(apiPaths.complaints, 'POST', body)).message
    close()
  }, { failure: 'complaints.failed' })
}
</script>

<template>
  <span
    v-if="user"
    class="complaint"
  >
    <button
      type="button"
      class="text-button complaint-button"
      :disabled="!!done"
      @click="open"
    >{{ done ? $t('complaints.sent') : $t('complaints.open') }}</button>
    <span
      v-if="done"
      class="visually-hidden"
      role="status"
    >{{ done }}</span>
    <!-- Окно выносится в body: внутри строки текста (p, span) блочный dialog недопустим по HTML. -->
    <Teleport to="body">
      <DialogForm
        ref="dialog"
        :title="$t(`complaints.title.${target}`)"
        :submit-text="$t('complaints.send')"
        :submit-disabled="busy || !reason"
        :error="error"
        @submit="send"
      >
        <fieldset class="complaint-reasons">
          <legend>{{ $t('complaints.reason') }}</legend>
          <label
            v-for="item in reasons"
            :key="item.value"
            class="consent-check"
          ><input
            v-model="reason"
            type="radio"
            :name="`${uid}-reason`"
            :value="item.value"
            required
          > {{ $t(`complaints.reasons.${item.value}`) }}</label>
        </fieldset>
        <label>{{ $t(textRequired ? 'complaints.text_required' : 'complaints.text') }}<textarea
          v-model="text"
          :required="textRequired"
          :maxlength="limits.complaint_text"
          rows="3"
          class="article-input"
        /></label>
      </DialogForm>
    </Teleport>
  </span>
</template>
