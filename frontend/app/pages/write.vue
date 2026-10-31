<script setup lang="ts">
const { user, loaded } = useAccount()
const { localePath } = useLocalization()
const { t } = useI18n()
useHead({ title: () => t('write.title') })
const form = reactive(emptyArticleForm())
const { busy, notice, save } = useArticleSave()

async function create() {
  const saved = await save(
    form,
    { path: apiPaths.articles, method: 'POST' },
    { success: 'write.submitted', failure: 'write.failed' },
  )

  if (saved) {
    Object.assign(form, emptyArticleForm())
  }
}
</script>

<template>
  <main class="auth-page wrap">
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
      class="form-card"
    >
      <PageHeading
        :eyebrow="$t('write.eyebrow')"
        :title="$t('write.title')"
      />
      <p class="form-intro">
        {{ $t('write.intro') }}
      </p>
      <form @submit.prevent="create">
        <ArticleFields v-model="form" />
        <FormMessage
          v-if="notice && !notice.error"
          :text="notice.text"
          tone="success"
        >
          <NuxtLink
            :to="localePath('/my-articles')"
            class="text-link"
          >{{ $t('menu.my_articles') }}</NuxtLink>
        </FormMessage>
        <FormMessage
          v-if="notice?.error"
          :text="notice.text"
          tone="error"
        />
        <button
          class="primary-button"
          :disabled="busy"
        >
          {{ $t('write.submit') }} <span aria-hidden="true">↗</span>
        </button>
      </form>
    </div>
  </main>
</template>
