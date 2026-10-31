<script setup lang="ts">
import type { ConsentStatus } from '~/composables/useLegal'

/** Согласия пользователя: принять новую версию или отозвать необязательное. */
const consents = defineModel<ConsentStatus[]>({ required: true })

const { send } = useApi()
const { refresh } = useAccount()
const { busy, error, run } = useFormAction()

const change = (type: string, accept: boolean) => run(async () => {
  consents.value = (await send<{ items: ConsentStatus[] }>(
    apiPaths.userConsents,
    'POST',
    accept ? { accept: [type] } : { revoke: [type] },
  )).items
  await refresh()
}, { failure: 'subscriptions.failed' })
</script>

<template>
  <AccountSection :title="$t('subscriptions.section')">
    <p class="form-intro">
      {{ $t('subscriptions.intro') }}
    </p>
    <ul class="consent-list">
      <li
        v-for="consent in consents"
        :key="consent.type"
      >
        <span><strong>{{ consent.title }}</strong> — {{
          consent.agreed ? $t('subscriptions.agreed') : $t('subscriptions.not_agreed')
        }}<small
          v-if="consent.required && consent.agreed && !consent.current"
          class="error"
        > {{ $t('subscriptions.outdated') }}</small></span>
        <button
          v-if="!consent.current"
          type="button"
          class="text-button text-button-inline"
          :disabled="busy"
          @click="change(consent.type, true)"
        >
          {{ $t('subscriptions.accept') }}
        </button>
        <button
          v-else-if="consent.revocable"
          type="button"
          class="text-button text-button-inline"
          :disabled="busy"
          @click="change(consent.type, false)"
        >
          {{ $t('subscriptions.revoke') }}
        </button>
      </li>
    </ul>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
  </AccountSection>
</template>
