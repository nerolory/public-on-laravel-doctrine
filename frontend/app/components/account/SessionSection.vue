<script setup lang="ts">
import type { Account } from '~/composables/useAccount'
import type { ConsentStatus } from '~/composables/useLegal'

/** Выход и удаление учётной записи с отзывом выбранных согласий. */
const props = defineProps<{ user: Account, consents: ConsentStatus[] }>()

const { send } = useApi()
const { refresh, leave, leaving } = useAccount()
const { localePath } = useLocalization()
const { busy, error, run } = useFormAction()
const { busy: leavingBusy, error: leaveError } = leaving
const password = ref('')
const revoke = ref<string[]>([])
/**
 * При удалении учётной записи отзываются все принятые согласия, в том числе обязательные.
 */
const revocable = computed(() => props.consents.filter(consent => consent.agreed))

async function remove() {
  const body = { password: password.value, revoke: revoke.value }

  if (await run(() => send(apiPaths.user, 'DELETE', body), { failure: 'account.delete_failed' })) {
    await refresh()
    await navigateTo(localePath('/login'))
  }
}
</script>

<template>
  <AccountSection>
    <button
      class="text-button"
      type="button"
      :disabled="leavingBusy"
      @click="leave(localePath('/login'))"
    >
      {{ $t('account.logout') }}
    </button>
    <FormMessage
      v-if="leaveError"
      :text="leaveError"
      tone="error"
    />
    <p
      v-if="!user.capabilities.delete_account"
      class="form-intro"
    >
      {{ $t('danger.owner') }}
    </p>
    <details
      v-else
      class="danger-zone"
    >
      <summary>{{ $t('account.delete') }}</summary>
      <p>{{ $t('account.delete_warning') }}</p>
      <form @submit.prevent="remove">
        <fieldset
          v-if="revocable.length"
          class="consents"
        >
          <legend>{{ $t('danger.revoke_title') }}</legend>
          <label
            v-for="consent in revocable"
            :key="consent.type"
            class="consent-check"
          ><input
            v-model="revoke"
            type="checkbox"
            :value="consent.type"
          > <span>{{ consent.title }}</span></label>
        </fieldset>
        <label>{{ $t('account.confirm_password') }}<input
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        ></label>
        <FormMessage
          v-if="error"
          :text="error"
          tone="error"
        />
        <button
          type="submit"
          class="danger-button"
          :disabled="busy"
        >
          {{ $t('account.delete') }}
        </button>
      </form>
    </details>
  </AccountSection>
</template>
