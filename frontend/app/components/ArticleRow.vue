<script setup lang="ts">
import type { ArticleListItem } from '~/composables/useArticles'

/** Строка статьи в списке: номер в списке, рубрика, заголовок и анонс. */
const { article, position } = defineProps<{ article: ArticleListItem, position: number }>()
const { localePath } = useLocalization()
</script>

<template>
  <NuxtLink
    :to="localePath(`/articles/${article.id}`)"
    class="article-row"
  ><span class="article-index">{{ String(position).padStart(2, '0') }}</span><div><span
    class="article-tag"
  >{{
    article.sections.length ? article.sections.join(' · ') : $t('home.article_tag')
  }}</span><h3>{{ article.title }}</h3><p>{{ article.excerpt }}</p></div><span
    class="article-arrow"
    aria-hidden="true"
  >↗</span></NuxtLink>
</template>
