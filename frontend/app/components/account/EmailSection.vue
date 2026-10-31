<script setup lang="ts">
import type { Account } from '~/composables/useAccount'

/** Адрес почты: подтверждение и смена. */
const props = defineProps<{ user: Account }>()

const { send } = useApi()
const { submit, refresh } = useAccount()
const { busy, notice, run } = useFormAction()
const limits = useFormLimits()
const email = ref(props.user.email)

watch(() => props.user.email, (value) => {
  email.value = value
})

const save = () => run(async () => {
  await submit(apiPaths.user, 'PUT', { email: email.value })
  await refresh()
}, { success: 'account.saved', failure: 'account.save_failed' })

const resend = () => run(() => send(apiPaths.emailResend, 'POST'), {
  success: 'email.sent',
  failure: 'account.save_failed',
})
</script>

<template>
  <AccountSection :title="$t('email.section')">
    <p :class="user.email_verified ? 'success' : 'error'">
      {{ user.email_verified ? $t('email.verified') : $t('email.not_verified') }}
    </p>
    <button
      v-if="!user.email_verified"
      type="button"
      class="text-button"
      :disabled="busy"
      @click="resend"
    >
      {{ $t('email.resend') }}
    </button>
    <form @submit.prevent="save">
      <label>{{ $t('account.email') }}<input
        v-model="email"
        type="email"
        autocomplete="email"
        required
        :maxlength="limits.email"
      ></label>
      <FormMessage
        v-if="notice"
        :text="notice.text"
        :tone="notice.error ? 'error' : 'success'"
      />
      <button
        type="submit"
        class="primary-button"
        :disabled="busy"
      >
        {{ $t('account.save') }} <span aria-hidden="true">↗</span>
      </button>
    </form>
  </AccountSection>
</template>
