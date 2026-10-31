<script setup lang="ts">
/**
 * Баннер согласия на cookies для регионов, где он обязателен. Выбор гостя хранится в cookie на год;
 * у вошедшего пользователя он ещё и записывается в журнал согласий на бэкенде.
 */
import { CONSENT_COOKIE, CONSENT_MAX_AGE, CONSENT_VERSION } from '~/metrics'
import type { Consent } from '~/metrics'
import { COOKIE_CATEGORIES, COOKIE_CONSENTS } from '~/composables/useLegal'

/** Выбор категорий без версии: категории берутся из выбора, который хранит cookie. */
type CookieChoice = Omit<Consent, 'v'>

const terms = useLegal()
const { user } = useAccount()
const { send } = useApi()
const { busy, error, run } = useFormAction()
const choice = useCookie<Consent | null>(CONSENT_COOKIE, {
  maxAge: CONSENT_MAX_AGE,
  sameSite: 'lax',
  default: () => null,
})
const customizing = ref(false)
const analytics = ref(false)
const marketing = ref(false)
const nuxtApp = useNuxtApp()

/** Баннер виден, пока нет выбора текущей версии; при ошибке записи в журнал он остаётся с сообщением. */
const visible = computed(() => terms.cookie_banner && (choice.value?.v !== CONSENT_VERSION || !!error.value))

/** Согласия на категории cookies с данным значением выбора. */
function consentTypes(next: CookieChoice, agreed: boolean): string[] {
  return COOKIE_CATEGORIES
    .filter(category => next[category] === agreed)
    .map(category => COOKIE_CONSENTS[category])
}

async function save(next: CookieChoice) {
  choice.value = { v: CONSENT_VERSION, ...next }
  // Счётчики подключаются сразу после согласия, без перезагрузки страницы.
  nuxtApp.$metrics.apply(choice.value)

  if (user.value) {
    const body = { accept: consentTypes(next, true), revoke: consentTypes(next, false) }

    await run(() => send(apiPaths.userConsents, 'POST', body), { failure: 'subscriptions.failed' })
  }
}
</script>

<template>
  <section
    v-if="visible"
    class="cookie-banner"
    aria-labelledby="cookie-banner-title"
  >
    <h2 id="cookie-banner-title">
      {{ $t('cookies.title') }}
    </h2>
    <p>{{ $t('cookies.text') }}</p>
    <div
      v-if="customizing"
      class="cookie-options"
    >
      <label><input
        type="checkbox"
        checked
        disabled
      > {{ $t('cookies.necessary') }}</label>
      <label><input
        v-model="analytics"
        type="checkbox"
      > {{ $t('cookies.analytics') }}</label>
      <label><input
        v-model="marketing"
        type="checkbox"
      > {{ $t('cookies.marketing') }}</label>
    </div>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
    <div class="cookie-actions">
      <button
        type="button"
        class="cookie-primary"
        :disabled="busy"
        @click="save({ analytics: true, marketing: true })"
      >
        {{ $t('cookies.accept_all') }}
      </button>
      <button
        type="button"
        class="cookie-secondary"
        :disabled="busy"
        @click="save({ analytics: false, marketing: false })"
      >
        {{ $t('cookies.reject') }}
      </button>
      <button
        v-if="!customizing"
        type="button"
        class="cookie-link"
        @click="customizing = true"
      >
        {{ $t('cookies.customize') }}
      </button>
      <button
        v-else
        type="button"
        class="cookie-secondary"
        :disabled="busy"
        @click="save({ analytics, marketing })"
      >
        {{ $t('cookies.save') }}
      </button>
    </div>
  </section>
</template>
