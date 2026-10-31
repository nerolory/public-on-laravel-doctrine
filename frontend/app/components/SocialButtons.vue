<script setup lang="ts">
/** Кнопки входа через соцсети, включённые в настройках сайта. */
type SocialProvider = { network: string, url: string }

const { t } = useI18n()
const { data, error } = await useApiFetch<{ items: SocialProvider[] }>(apiPaths.socialProviders)
const providers = computed(() => data.value?.items ?? [])
</script>

<template>
  <FormMessage
    v-if="error"
    :text="$t('social.providers_unavailable')"
    tone="error"
  />
  <div
    v-else-if="providers.length"
    class="social-buttons"
  >
    <p class="social-or">
      {{ $t('social.or') }}
    </p>
    <a
      v-for="item in providers"
      :key="item.network"
      :href="item.url"
      class="social-button"
      :class="`social-${item.network}`"
    >{{ t('social.continue', { provider: t(`social.providers.${item.network}`) }) }}</a>
  </div>
</template>
