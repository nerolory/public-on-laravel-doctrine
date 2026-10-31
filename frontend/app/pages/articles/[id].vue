<script setup lang="ts">
import type { ArticleStatus, PollViewData } from '~/composables/useArticles'
import type { ReactionSummary } from '~/composables/useComments'
import type { ApiHead } from '~/composables/useApiHead'
import type { DialogHandle } from '~/composables/useDialog'
import type { ChartResult } from '~~/editor/rich-editor'
import type { ImageView } from '#shared/types/content'

type ArticleView = {
  id: number
  title: string
  content: string
  cover: ImageView | null
  sections: string[]
  tags: string[]
  status: ArticleStatus
  rejection_reason: string | null
  published_at: string | null
  view_count: number
  author: { id: number, name: string } | null
  permissions: { edit: boolean, delete: boolean, moderate: boolean, react: boolean }
  reactions: ReactionSummary
  poll: PollViewData | null
  head: ApiHead
  share: { network: string, url: string }[]
  /** Данные графиков текста по UUID. */
  charts: Record<string, ChartResult>
}

const route = useRoute()
const { localePath, locale } = useLocalization()
const { t } = useI18n()
const { data: article, error } = await useApiFetch<ArticleView>(() => apiPaths.article(String(route.params.id)))
useApiHead(() => article.value?.head)

const body = ref<HTMLElement | null>(null)

useArticleCharts(body, () => article.value?.charts)
useHead(() => (article.value ? {} : { title: t('article.default_title', { site: t('site.name') }) }))

const hasActions = computed(() => !!article.value && Object.values(article.value.permissions).some(Boolean))
const reactionsPath = computed(() => article.value ? apiPaths.articleReactions(article.value.id) : '')
const moderatePath = computed(() => article.value ? adminPaths.articleForm(article.value.id) : '')
const archiveDialog = ref<DialogHandle | null>(null)
const { send } = useApi()
// Просмотр отправляется из браузера после загрузки страницы: запросы без выполнения скриптов не учитываются.
onMounted(() => {
  if (article.value?.status !== 'published') {
    return
  }

  // Статья уже открыта, поэтому ошибка учёта просмотра посетителю не показывается и только пишется в консоль.
  send(apiPaths.articleView(article.value.id), 'POST')
    .catch((failure: unknown) => console.error('Просмотр статьи не учтён', failure))
  useNuxtApp().$metrics.goal('article_view')
})
async function onArchived() {
  await navigateTo({ path: localePath('/my-articles'), query: { archived: '1' } })
}
</script>

<template>
  <main class="wrap reading">
    <BackLink :to="localePath('/#articles')">
      {{ $t('nav.all_articles') }}
    </BackLink>
    <div
      v-if="error"
      class="state"
    >
      {{ $t('article.not_found') }}
    </div>
    <article v-else-if="article">
      <StatusBanner
        v-if="article.status !== 'published'"
        :status="article.status"
        :label="$t(`article_status.${article.status}`)"
        :note="article.permissions.edit ? $t(`article.status_note.${article.status}`) : undefined"
        :rejection-reason="article.rejection_reason"
      />
      <p class="eyebrow">
        {{
          article.sections.length ? article.sections.join(' · ') : $t('article.story')
        }} / {{ String(article.id).padStart(2, '0') }}
      </p>
      <h1>{{ article.title }}</h1>
      <p class="article-meta">
        <NuxtLink
          v-if="article.author"
          :to="localePath(`/authors/${article.author.id}`)"
          class="text-link"
        >{{ article.author.name }}</NuxtLink>
        <NuxtTime
          v-if="article.published_at"
          :datetime="article.published_at"
          :locale="locale"
          date-style="long"
        />
        <span>{{ $t('article.views', { count: article.view_count }) }}</span>
      </p>
      <div
        v-if="hasActions"
        class="article-actions"
        role="group"
        :aria-label="$t('article.actions')"
      >
        <NuxtLink
          v-if="article.permissions.edit"
          :to="localePath(`/my-articles/${article.id}`)"
          class="text-link"
        >{{ $t(article.status === 'rejected' ? 'article.fix' : 'article.edit') }}</NuxtLink>
        <button
          v-if="article.permissions.delete"
          type="button"
          class="text-button text-button-inline danger-text"
          @click="archiveDialog?.open()"
        >
          {{ $t('article.delete') }}
        </button>
        <a
          v-if="article.permissions.moderate"
          :href="moderatePath"
          class="text-link"
        >{{ $t('article.moderate') }}</a>
      </div>
      <img
        v-if="article.cover"
        :src="article.cover.url"
        :srcset="article.cover.srcset"
        :sizes="IMAGE_SIZES.articleCover"
        :width="article.cover.width"
        :height="article.cover.height"
        alt=""
        class="article-cover"
        fetchpriority="high"
      >
      <div class="reading-rule" />
      <!-- HTML статьи очищен на сервере белым списком тегов (App\Services\Article\ArticleContent). -->
      <!-- eslint-disable vue/no-v-html -->
      <div
        ref="body"
        class="reading-content article-body"
        v-html="article.content"
      />
      <!-- eslint-enable vue/no-v-html -->
      <PollView
        v-if="article.poll"
        :poll="article.poll"
      />
      <ul
        v-if="article.tags.length"
        class="tag-chips article-tags"
        :aria-label="$t('article.tags')"
      >
        <li
          v-for="tag in article.tags"
          :key="tag"
          class="tag-chip"
        >
          {{ tag }}
        </li>
      </ul>
      <p
        v-if="article.status === 'published' && !article.permissions.edit"
        class="complaint-line"
      >
        <ComplaintButton
          :id="article.id"
          target="article"
        />
      </p>
      <ShareButtons :links="article.share" />
      <ReactionBar
        v-if="article.status === 'published'"
        class="article-reactions"
        :summary="article.reactions"
        :endpoint="reactionsPath"
        :can-react="article.permissions.react"
      />
      <CommentsSection
        v-if="article.status === 'published'"
        :article-id="article.id"
      />
      <ArchiveArticleDialog
        v-if="article.permissions.delete"
        ref="archiveDialog"
        :article-id="article.id"
        :title="article.title"
        @archived="onArchived"
      />
    </article>
  </main>
</template>
