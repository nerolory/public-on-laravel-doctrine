<script setup lang="ts">
/** Telegram возвращает данные входа во фрагменте #tgAuthResult: фронт только передаёт их бэкенду на проверку. */
const { refresh } = useAccount()
const { send } = useApi()
const { localePath } = useLocalization()
const { t } = useI18n()
useHead({ title: () => t('social.providers.telegram') })
const { error, run } = useFormAction()

/** Ответ бэкенда определяет следующий шаг: вход выполнен, нужна регистрация или проверка не прошла. */
async function complete() {
  await run(async () => {
    const encoded = new URLSearchParams(location.hash.slice(1)).get('tgAuthResult')

    if (encoded === null) {
      throw new Error('Telegram не передал данные входа')
    }

    const data: unknown = JSON.parse(atob(encoded.replace(/-/g, '+').replace(/_/g, '/')))
    const { next } = await send<{ next: string }>(apiPaths.telegram, 'POST', { data })

    if (next === 'logged_in') {
      await refresh()

      return navigateTo(localePath('/account'))
    }

    if (next === 'pending') {
      return navigateTo(localePath('/register/social'))
    }

    throw new Error(`Неизвестный шаг входа через Telegram: ${next}`)
  }, { failure: 'social.telegram_failed' })
}

onMounted(complete)
</script>

<template>
  <main class="auth-page wrap">
    <div class="form-card">
      <FormMessage
        v-if="error"
        :text="error"
        tone="error"
      >
        <NuxtLink
          :to="localePath('/login')"
          class="text-link"
        >{{ $t('nav.login') }}</NuxtLink>
      </FormMessage>
      <p
        v-else
        class="state"
        role="status"
      >
        {{ $t('social.telegram_checking') }}
      </p>
    </div>
  </main>
</template>
