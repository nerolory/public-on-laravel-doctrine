<script setup lang="ts">
const { submit, refresh } = useAccount()
const { localePath } = useLocalization()
const legal = useLegal()
const { t } = useI18n()
const limits = useFormLimits()
useHead({ title: () => t('register.title'), meta: [{ name: 'description', content: () => t('register.intro') }] })
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const consents = ref<Record<string, boolean>>({})
const { busy, error, run } = useFormAction()

async function register() {
  const registered = await run(async () => {
    await submit(apiPaths.register, 'POST', { ...form, consents: consents.value })
    await refresh()
  }, { failure: 'register.failed' })

  if (registered) {
    useNuxtApp().$metrics.goal('registration')
    await navigateTo(localePath('/account'))
  }
}
</script>

<template>
  <main class="auth-page wrap">
    <BackLink :to="localePath('/')">
      {{ $t('nav.back_home') }}
    </BackLink>
    <div class="form-card">
      <PageHeading
        :eyebrow="$t('register.eyebrow')"
        :title="$t('register.title')"
      />
      <p class="form-intro">
        {{ $t('register.intro') }}
      </p>
      <form @submit.prevent="register">
        <label>{{ $t('register.name') }}<input
          v-model="form.name"
          autocomplete="given-name"
          required
          :maxlength="limits.person_name"
        ></label>
        <label>{{ $t('register.email') }}<input
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          :maxlength="limits.email"
        ></label>
        <label>{{ $t('register.password') }}<input
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          required
          :minlength="limits.password"
        ></label>
        <label>{{ $t('register.password_confirmation') }}<input
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          required
          :minlength="limits.password"
        ></label>
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
          {{ busy ? $t('register.submitting') : $t('register.submit') }} <span aria-hidden="true">↗</span>
        </button>
      </form>
      <SocialButtons />
      <p class="form-foot">
        {{ $t('register.have_account') }} <NuxtLink :to="localePath('/login')">{{ $t('register.login') }}</NuxtLink>
      </p>
    </div>
  </main>
</template>
