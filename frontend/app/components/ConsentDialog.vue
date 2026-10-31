<script setup lang="ts">
import type { DialogControls } from '~/composables/useDialog'

/** Окно новых правил: открывается, когда бэкенд требует принять обновлённые обязательные документы. */
const { open, documents, close } = useConsentGate()
const { send } = useApi()
const { busy, error, run, reset } = useFormAction()
const dialog = ref<DialogControls | null>(null)

watch(open, (value) => {
  reset()

  if (value) {
    dialog.value?.open()

    return
  }

  dialog.value?.close()
})

async function accept() {
  const accepted = await run(
    () => send(apiPaths.userConsents, 'POST', { accept: documents.value.map(item => item.type) }),
    { failure: 'consent_dialog.failed' },
  )

  if (accepted) {
    close(true)
  }
}
</script>

<template>
  <AppDialog
    ref="dialog"
    v-slot="{ titleId }"
    persistent
    @cancel="close(false)"
  >
    <h2 :id="titleId">
      {{ $t('consent_dialog.title') }}
    </h2>
    <p>{{ $t('consent_dialog.intro') }}</p>
    <ConsentDocument
      v-for="item in documents"
      :key="item.type"
      :summary="item.title"
      :text="item.text"
      open
    />
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
    <div class="dialog-actions">
      <button
        type="button"
        class="primary-button"
        :disabled="busy"
        @click="accept"
      >
        {{ $t('consent_dialog.accept') }} <span aria-hidden="true">↗</span>
      </button>
      <button
        type="button"
        class="text-button"
        @click="close(false)"
      >
        {{ $t('consent_dialog.cancel') }}
      </button>
    </div>
  </AppDialog>
</template>
