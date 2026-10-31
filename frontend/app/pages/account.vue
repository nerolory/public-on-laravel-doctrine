<script setup lang="ts">
import type { ConsentStatus } from '~/composables/useLegal'

const { user, loaded } = useAccount()
const { localePath } = useLocalization()
const { t } = useI18n()
const { get } = useApi()
const route = useRoute()
const consents = ref<ConsentStatus[]>([])
const { error: consentsError, run: loadConsents } = useFormAction()
/** Результат перехода по ссылке подтверждения почты: ?verified=1 или 0. */
const verified = computed(() => route.query.verified)
const exportUrl = apiPaths.userExport

useHead({ title: () => t('account.title') })

onMounted(() => {
  if (user.value) {
    loadConsents(async () => {
      consents.value = (await get<{ items: ConsentStatus[] }>(apiPaths.userConsents)).items
    }, { failure: 'account.consents_load_error' })
  }
})
</script>

<template>
  <main class="auth-page wrap account-page">
    <BackLink :to="localePath('/')">
      {{ $t('nav.back_home') }}
    </BackLink>
    <div
      v-if="!loaded"
      class="state"
    >
      {{ $t('common.loading') }}
    </div>
    <LoginRequired v-else-if="!user" />
    <div
      v-else
      class="account-layout"
    >
      <header class="account-head">
        <PageHeading
          :eyebrow="$t('account.eyebrow')"
          :title="user.name"
        >
          <NuxtLink
            :to="localePath(`/authors/${user.id}`)"
            class="text-link"
          >
            {{ $t('profile.public_link') }}
          </NuxtLink>
          <FormMessage
            v-if="verified === '1' || verified === '0'"
            :text="$t(verified === '1' ? 'email.just_verified' : 'email.verify_failed')"
            :tone="verified === '1' ? 'success' : 'error'"
          />
        </PageHeading>
      </header>

      <AccountAvatarSection :user="user" />
      <AccountProfileSection :user="user" />
      <AccountEmailSection :user="user" />
      <FormMessage
        v-if="consentsError"
        :text="consentsError"
        tone="error"
      />
      <AccountConsentsSection v-model="consents" />

      <AccountSection
        v-if="user.capabilities.export_data"
        :title="$t('export.section')"
      >
        <p class="form-intro">
          {{ $t('export.intro') }}
        </p>
        <a
          class="text-link"
          :href="exportUrl"
          download="my-data.json"
        >{{ $t('export.button') }}</a>
      </AccountSection>

      <AccountSessionSection
        :user="user"
        :consents="consents"
      />
    </div>
  </main>
</template>
