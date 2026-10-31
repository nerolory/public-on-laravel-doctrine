<script setup lang="ts">
import type { ArticleListItem } from '~/composables/useArticles'
import type { ListPage } from '#shared/types/content'

/**
 * Список статей с подгрузкой: source — первая страница, load — запрос следующей страницы по номеру.
 * Пустой список выводит слот empty.
 */
const props = defineProps<{
  source: ListPage<ArticleListItem> | null | undefined
  load: (page: number) => Promise<ListPage<ArticleListItem>>
}>()
const { items, hasMore, busy, error, more } = usePagedList(
  () => props.source,
  page => props.load(page),
  'home.more_error',
)
</script>

<template>
  <div
    v-if="items.length"
    class="article-list"
  >
    <ArticleRow
      v-for="(article, i) in items"
      :key="article.id"
      :article="article"
      :position="i + 1"
    />
  </div>
  <slot
    v-else
    name="empty"
  />
  <LoadMore
    v-if="hasMore"
    :busy="busy"
    :error="error"
    @more="more"
  />
</template>
