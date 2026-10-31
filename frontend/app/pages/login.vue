<script setup lang="ts">
const { submit, refresh } = useAccount()
const { localePath } = useLocalization()
const { t } = useI18n()
const route = useRoute()
useHead({ title: () => t('login.title'), meta: [{ name: 'description', content: () => t('login.intro') }] })
const form = reactive({ email: '', password: '' })
const { busy, error, run } = useFormAction()
/** Ошибка соцсети приходит в адресе страницы; сообщение гаснет при новой попытке входа. */
const socialFailed = ref(!!route.query.social_error)
const message = computed(() => error.value || (socialFailed.value ? t('social.error') : ''))

async function login() {
  socialFailed.value = false

  const signedIn = await run(async () => {
    await submit(apiPaths.login, 'POST', form)
    await refresh()
  }, { failure: 'login.failed' })

  if (signedIn) {
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
        :eyebrow="$t('login.eyebrow')"
        :title="$t('login.title')"
      />
      <p class="form-intro">
        {{ $t('login.intro') }}
      </p>
      <form @submit.prevent="login">
        <label>{{ $t('login.email') }}<input
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
        ></label>
        <label>{{ $t('login.password') }}<input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          required
        ></label>
        <FormMessage
          v-if="message"
          :text="message"
          tone="error"
        />
        <button
          class="primary-button"
          :disabled="busy"
        >
          {{ busy ? $t('login.submitting') : $t('login.submit') }} <span aria-hidden="true">↗</span>
        </button>
      </form>
      <SocialButtons />
      <p class="form-foot">
        {{ $t('login.no_account') }} <NuxtLink :to="localePath('/register')">{{ $t('login.register') }}</NuxtLink>
      </p>
    </div>
  </main>
</template>
