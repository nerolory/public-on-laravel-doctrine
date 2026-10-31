<script setup lang="ts">
const { refresh } = useAccount()
const { send } = useApi()
const { localePath } = useLocalization()
const legal = useLegal()
const { t } = useI18n()
const limits = useFormLimits()
useHead({ title: () => t('social.pending_title') })
const { data: pending, error: pendingError } = await useApiFetch<{ provider: string, email: string | null }>(
  apiPaths.socialPending,
)
const email = ref(pending.value?.email ?? '')
const consents = ref<Record<string, boolean>>({})
const { busy, error, run } = useFormAction()

async function complete() {
  const completed = await run(async () => {
    await send(apiPaths.socialComplete, 'POST', { email: email.value, consents: consents.value })
    await refresh()
  }, { failure: 'social.error' })

  if (completed) {
    useNuxtApp().$metrics.goal('registration')
    await navigateTo(localePath('/account'))
  }
}
</script>

<template>
  <main class="auth-page wrap">
    <BackLink :to="localePath('/login')">
      {{ $t('nav.back_home') }}
    </BackLink>
    <div class="form-card">
      <PageHeading
        :eyebrow="$t('social.pending_eyebrow')"
        :title="$t('social.pending_title')"
      />
      <FormMessage
        v-if="pendingError"
        :text="$t(pendingError.statusCode === 404 ? 'social.expired' : 'social.error')"
        tone="error"
      />
      <template v-else-if="pending">
        <p class="form-intro">
          {{ $t('social.pending_intro', { provider: $t(`social.providers.${pending.provider}`) }) }}
        </p>
        <form @submit.prevent="complete">
          <label>{{ $t('register.email') }}<input
            v-model="email"
            type="email"
            autocomplete="email"
            required
            :maxlength="limits.email"
            :aria-describedby="pending.email ? undefined : 'social-email-hint'"
          ></label>
          <p
            v-if="!pending.email"
            id="social-email-hint"
            class="form-intro"
          >
            {{ $t('social.email_hint') }}
          </p>
          <ConsentCheckboxes
            v-model="consents"
            :documents="legal.documents"
          />
          <FormMessage
            v-if="error"
            :text="error"
            tone="error"
          />
          <button
            class="primary-button"
            :disabled="busy"
          >
            {{ $t('social.complete') }} <span aria-hidden="true">↗</span>
          </button>
        </form>
      </template>
    </div>
  </main>
</template>
