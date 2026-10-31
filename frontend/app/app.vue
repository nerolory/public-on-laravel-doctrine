<script setup lang="ts">
const { user, loaded, refresh } = useAccount()
const { config, locale, localePath, switchUrl } = useLocalization()
const { t } = useI18n()
const route = useRoute()

if (!loaded.value) {
  await refresh()
}

useHead({ htmlAttrs: { lang: locale }, titleTemplate: title => title || t('site.name') })

/** Прокрутка в пикселях, после которой появляется кнопка «наверх»: примерно высота первого экрана. */
const BACK_TO_TOP_OFFSET = 500

const showBackToTop = ref(false)
function updateScrollPosition() {
  showBackToTop.value = window.scrollY > BACK_TO_TOP_OFFSET
}
function backToTop() {
  window.scrollTo({
    top: 0,
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
  })
}
onMounted(() => {
  updateScrollPosition()
  window.addEventListener('scroll', updateScrollPosition, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', updateScrollPosition))
</script>

<template>
  <div class="app-shell">
    <header class="header">
      <div class="header-inner">
        <NuxtLink
          :to="localePath('/')"
          class="brand"
        ><span
          class="brand-mark"
          aria-hidden="true"
        >с<span>·</span>с</span><span><SiteBrand /><small
          class="caps"
        >{{ $t('site.tagline') }}</small></span></NuxtLink>
        <nav :aria-label="$t('nav.main')">
          <NuxtLink :to="localePath('/#articles')">{{ $t('nav.articles') }}</NuxtLink>
          <NuxtLink :to="localePath('/#about')">{{ $t('nav.about') }}</NuxtLink>
        </nav>
        <div class="header-actions">
          <nav
            v-if="config.languages.length > 1"
            class="language-switch"
            :aria-label="$t('nav.language')"
          >
            <a
              v-for="language in config.languages"
              :key="language.code"
              :href="switchUrl(language.code, route.fullPath)"
              :lang="language.code"
              :hreflang="language.code"
              :aria-current="language.code === locale ? 'true' : undefined"
              :class="{ active: language.code === locale }"
            >{{ language.code.toUpperCase() }}</a>
          </nav>
          <AccountMenu v-if="loaded && user" />
          <NuxtLink
            v-else
            class="account-link"
            :to="localePath('/login')"
          >{{ $t('nav.login') }} <span aria-hidden="true">↗</span></NuxtLink>
        </div>
      </div>
    </header>
    <NuxtPage />
    <footer class="footer">
      <div class="footer-inner">
        <div>
          <div class="footer-brand">
            <SiteBrand /><span aria-hidden="true">✳</span>
          </div><p>{{ $t('footer.about') }}</p>
        </div><div class="footer-meta caps">
          <span>{{ $t('footer.magazine') }}</span><span>{{ $t('footer.made_for') }}</span><span>© <NuxtTime
            :datetime="Date.now()"
            :locale="locale"
            year="numeric"
          /></span>
        </div>
      </div>
    </footer>
    <ConsentDialog />
    <CookieBanner />
    <button
      v-if="showBackToTop"
      class="back-to-top"
      type="button"
      :aria-label="$t('nav.back_to_top')"
      :title="$t('nav.to_top')"
      @click="backToTop"
    >
      ↑
    </button>
  </div>
</template>
