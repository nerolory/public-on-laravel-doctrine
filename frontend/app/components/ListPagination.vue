<script setup lang="ts">
/** Переход между страницами списка; ссылки сохраняют остальные параметры адреса, например фильтр. */
const { page, hasMore } = defineProps<{ page: number, hasMore: boolean }>()
const route = useRoute()
</script>

<template>
  <nav
    class="pagination"
    :aria-label="$t('my_articles.pages')"
  >
    <NuxtLink
      v-if="page > 1"
      :to="{ query: { ...route.query, page: page - 1 } }"
    >← {{ $t('my_articles.prev') }}</NuxtLink>
    <span aria-current="page">{{ page }}</span>
    <NuxtLink
      v-if="hasMore"
      :to="{ query: { ...route.query, page: page + 1 } }"
    >{{ $t('my_articles.next') }} →</NuxtLink>
  </nav>
</template>
