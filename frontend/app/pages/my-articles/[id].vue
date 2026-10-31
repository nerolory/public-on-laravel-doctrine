<script setup lang="ts">
import type { PollFormData } from '~/composables/useArticles'
import type { UploadedImage } from '~~/editor/rich-editor'

type EditableArticle = {
  id: number
  title: string
  content: string
  summary: string | null
  version: number
  section_ids: number[]
  tags: string[]
  cover: UploadedImage | null
  poll: PollFormData | null
  rejection_reason: string | null
}

const route = useRoute()
const { localePath } = useLocalization()
const { t } = useI18n()
const { data: article, error, refresh } = await useApiFetch<EditableArticle>(
  () => apiPaths.myArticle(String(route.params.id)),
)
useHead({ title: () => t('edit.title') })

const form = reactive(emptyArticleForm())
watch(article, (value) => {
  if (value) {
    Object.assign(form, {
      title: value.title,
      content: value.content,
      summary: value.summary ?? '',
      section_ids: value.section_ids,
      tags: value.tags,
      cover: value.cover,
      poll: pollForForm(value.poll),
    })
  }
}, { immediate: true })

const { busy, error: saveError, conflict, save, reset } = useArticleSave()

async function saveEdit() {
  const current = article.value

  if (!current) {
    throw new Error('Форма правки открыта без загруженной статьи')
  }

  const saved = await save(
    form,
    { path: apiPaths.myArticle(String(route.params.id)), method: 'PUT', version: current.version },
    { failure: 'edit.failed' },
  )

  if (saved) {
    await navigateTo({ path: localePath('/my-articles'), query: { saved: '1' } })
  }
}

/**
 * Перезагрузка подтягивает актуальную версию статьи: watch заполняет форму заново,
 * поэтому несохранённые правки теряются.
 */
async function reload() {
  await refresh()
  reset()
}
</script>

<template>
  <main class="auth-page wrap">
    <BackLink :to="localePath('/my-articles')">
      {{ $t('edit.back') }}
    </BackLink>
    <LoginRequired v-if="error?.statusCode === 401" />
    <div
      v-else-if="error"
      class="state"
    >
      {{ $t('article.not_found') }}
    </div>
    <div
      v-else-if="article"
      class="form-card"
    >
      <PageHeading
        :eyebrow="$t('edit.eyebrow')"
        :title="$t('edit.title')"
      />
      <p class="form-intro">
        {{ $t('edit.intro') }}
      </p>
      <RejectionNote
        v-if="article.rejection_reason"
        :reason="article.rejection_reason"
      />
      <form @submit.prevent="saveEdit">
        <ArticleFields v-model="form" />
        <div
          v-if="saveError"
          class="error"
          role="alert"
        >
          <p>{{ saveError }}</p>
          <button
            v-if="conflict"
            type="button"
            class="text-button"
            @click="reload"
          >
            {{ $t('edit.reload') }}
          </button>
        </div>
        <button
          class="primary-button"
          :disabled="busy"
        >
          {{ $t('edit.submit') }} <span aria-hidden="true">↗</span>
        </button>
      </form>
    </div>
  </main>
</template>
