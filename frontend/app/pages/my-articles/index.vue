<script setup lang="ts">
import type { ArticleStatus } from '~/composables/useArticles'
import type { DialogHandle } from '~/composables/useDialog'
import type { ListPage } from '#shared/types/content'

type MyArticle = {
  id: number
  title: string
  excerpt: string
  status: ArticleStatus
  rejection_reason: string | null
  view_count: number
  updated_at: string
}

/** Вкладки списка: «все» и статусы модерации. */
const TABS = ['all', 'pending', 'rejected', 'published'] as const
type Tab = typeof TABS[number]
type MyArticlePage = ListPage<MyArticle> & { counts: Record<Tab, number> }

function tabOf(value: unknown): Tab {
  return TABS.find(item => item === value) ?? 'all'
}

const route = useRoute()
const { localePath, locale } = useLocalization()
const { t } = useI18n()
useHead({ title: () => t('my_articles.title') })

const tab = computed(() => tabOf(route.query.status))
const page = usePageQuery()
const { data, error, refresh } = await useApiFetch<MyArticlePage>(apiPaths.myArticles, {
  query: computed(() => ({ page: page.value, ...(tab.value === 'all' ? {} : { status: tab.value }) })),
})
const notice = computed(() =>
  route.query.saved
    ? t('my_articles.saved')
    : route.query.archived
      ? t('my_articles.archived')
      : '',
)

const archiving = ref<MyArticle | null>(null)
const archiveDialog = ref<DialogHandle | null>(null)
async function askArchive(article: MyArticle) {
  archiving.value = article
  await nextTick()
  archiveDialog.value?.open()
}
async function onArchived() {
  archiving.value = null
  await navigateTo({ query: { ...(tab.value === 'all' ? {} : { status: tab.value }), archived: '1' } })
  await refresh()
}
</script>

<template>
  <main class="wrap reading">
    <BackLink :to="localePath('/')">
      {{ $t('nav.back_home') }}
    </BackLink>
    <LoginRequired v-if="error?.statusCode === 401" />
    <div
      v-else-if="error"
      class="state"
    >
      {{ $t('my_articles.load_error') }}
    </div>
    <section
      v-else-if="data"
      class="account-layout my-articles"
    >
      <header class="account-head">
        <PageHeading
          :eyebrow="$t('my_articles.eyebrow')"
          :title="$t('my_articles.title')"
        >
          <NuxtLink
            :to="localePath('/write')"
            class="text-link"
          >{{ $t('my_articles.write') }} <span aria-hidden="true">↗</span></NuxtLink>
        </PageHeading>
      </header>
      <nav
        class="section-filter"
        :aria-label="$t('my_articles.filter')"
      >
        <div class="filter-row">
          <NuxtLink
            v-for="item in TABS"
            :key="item"
            :to="{ query: item === 'all' ? {} : { status: item } }"
            class="filter-chip"
            :class="{ active: tab === item }"
            :aria-current="tab === item ? 'page' : undefined"
          >
            {{ $t(`my_articles.tabs.${item}`) }} <span class="chip-count">{{ data.counts[item] }}</span>
          </NuxtLink>
        </div>
      </nav>
      <FormMessage
        v-if="notice"
        :text="notice"
        tone="success"
      />
      <EmptyState
        v-if="!data.items.length"
        heading="h2"
        :title="$t('my_articles.empty_title')"
      >
        {{ $t('my_articles.empty_text') }}
      </EmptyState>
      <ul
        v-else
        class="my-article-list"
      >
        <OwnedRow
          v-for="article in data.items"
          :key="article.id"
          :title="article.title"
          :status="article.status"
          :status-label="$t(`article_status.${article.status}`)"
          :to="localePath(`/articles/${article.id}`)"
          :edit-to="localePath(`/my-articles/${article.id}`)"
          :edit-label="$t(article.status === 'rejected' ? 'article.fix' : 'article.edit')"
          :rejection-reason="article.rejection_reason"
          @delete="askArchive(article)"
        >
          <template #meta>
            <span>
              {{ $t('my_articles.updated') }}
              <NuxtTime
                :datetime="article.updated_at"
                :locale="locale"
                date-style="medium"
              />
            </span>
            <span>{{ $t('article.views', { count: article.view_count }) }}</span>
          </template>
          <p class="my-article-excerpt">
            {{ article.excerpt }}
          </p>
        </OwnedRow>
      </ul>
      <ListPagination
        v-if="page > 1 || data.has_more"
        :page="page"
        :has-more="data.has_more"
      />
    </section>
    <ArchiveArticleDialog
      v-if="archiving"
      ref="archiveDialog"
      :article-id="archiving.id"
      :title="archiving.title"
      @archived="onArchived"
    />
  </main>
</template>
