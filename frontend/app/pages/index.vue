<script setup lang="ts">
import type { ApiHead } from '~/composables/useApiHead'
import type { ArticleListItem } from '~/composables/useArticles'
import type { ListPage } from '#shared/types/content'

/** Ряд фильтра: all — адрес ряда целиком (null для корневого), active — выбранный подраздел (null, если нет). */
type FilterRow = {
  key: string
  all: string | null
  all_label: 'all' | 'whole_section'
  active: string | null
  items: { slug: string, name: string }[]
}

const route = useRoute()
const selected = computed<string | null>(() => {
  const section = route.query.section

  return typeof section === 'string' && section !== '' ? section : null
})
const sectionQuery = computed(() => (selected.value ? { section: selected.value } : {}))
const { get } = useApi()
const { t } = useI18n()
const { data: head, error: headError } = await useApiFetch<ApiHead>(apiPaths.head, { query: sectionQuery })

// Метаданные главной приходят только из ответа головы, поэтому её ошибка останавливает страницу.
if (headError.value) {
  throw createError({ statusCode: 500, message: t('home.head_failed'), cause: headError.value })
}

useApiHead(() => head.value)
// Ряды фильтра строит сервер: какие разделы показать и какой из них выбран.
const { data: filter, error: filterError } = await useApiFetch<{ rows: FilterRow[] }>(apiPaths.sectionFilter, {
  query: sectionQuery,
})
function filterQuery(page: number): Record<string, string | number> {
  return selected.value ? { page, section: selected.value } : { page }
}
function sectionLink(slug: string | null) {
  return { query: slug ? { section: slug } : {} }
}

const { data, status, error } = await useApiFetch<ListPage<ArticleListItem>>(apiPaths.articles, {
  query: computed(() => filterQuery(1)),
})
/** Следующие страницы ленты с тем же фильтром. */
const loadPage = (page: number) => get<ListPage<ArticleListItem>>(apiPaths.articles, filterQuery(page))
</script>

<template>
  <main>
    <section class="hero wrap">
      <div class="hero-main">
        <p class="eyebrow caps">
          <span class="eyebrow-dot" /> {{ $t('home.eyebrow') }} <span class="slash">/</span> {{ $t('home.issue') }}
        </p><h1>{{ $t('home.title_line1') }}<br><em>{{ $t('home.title_line2') }}</em><br>{{
          $t('home.title_line3')
        }}<span
          class="period"
        >.</span></h1><div
          class="hero-bottom"
        >
          <p>{{ $t('home.lead') }}</p><a
            class="round-arrow"
            href="#articles"
            :aria-label="$t('home.to_articles')"
          >↘</a>
        </div>
      </div><aside class="hero-aside">
        <div
          class="hero-art"
          aria-hidden="true"
        >
          <div class="art-orbit orbit-one" /><div class="art-orbit orbit-two" /><div class="art-star">
            ✳
          </div><span class="art-label caps">{{ $t('home.art_label') }}</span><span
            class="art-number"
          >{{ $t('home.art_number') }}</span>
        </div><div class="aside-caption caps">
          <span>{{ $t('home.discover') }}</span><span>01 / ∞</span>
        </div>
      </aside>
    </section>
    <div class="intro-band">
      <div class="wrap intro-inner">
        <span
          aria-hidden="true"
        >✳</span><p>{{ $t('home.band_text') }} <em>{{ $t('home.band_accent') }}</em></p><span
          class="caps"
        >{{ $t('home.band_side') }}</span>
      </div>
    </div>
    <section
      id="articles"
      class="articles wrap"
    >
      <div class="section-heading">
        <div>
          <p class="eyebrow caps">
            {{ $t('home.library') }}
          </p><h2>{{ $t('home.fresh') }} <em>{{ $t('home.stories') }}</em></h2>
        </div><p>{{ $t('home.library_lead') }}</p>
      </div>
      <FormMessage
        v-if="filterError"
        :text="$t('write.sections_error')"
        tone="error"
      />
      <nav
        v-else-if="filter && filter.rows[0]?.items.length"
        class="section-filter"
        :aria-label="$t('filter.label')"
      >
        <div
          v-for="(row, index) in filter.rows"
          :key="row.key"
          class="filter-row"
          :class="{ 'filter-row-sub': index > 0 }"
        >
          <NuxtLink
            :to="sectionLink(row.all)"
            class="filter-chip"
            :class="{ active: selected === row.all }"
            :aria-current="selected === row.all ? 'page' : undefined"
          >{{ $t(`filter.${row.all_label}`) }}</NuxtLink>
          <NuxtLink
            v-for="section in row.items"
            :key="section.slug"
            :to="sectionLink(section.slug)"
            class="filter-chip"
            :class="{ active: row.active === section.slug }"
            :aria-current="selected === section.slug ? 'page' : undefined"
          >{{ section.name }}</NuxtLink>
        </div>
      </nav>
      <div
        v-if="status === 'pending'"
        class="state"
      >
        {{ $t('home.loading') }}
      </div>
      <EmptyState
        v-else-if="error?.statusCode === 404"
        heading="h3"
        :title="$t('home.section_missing_title')"
      >
        {{ $t('home.section_missing_text') }} <NuxtLink
          :to="sectionLink(null)"
          class="text-link"
        >{{ $t('home.show_all') }}</NuxtLink>
      </EmptyState>
      <div
        v-else-if="error"
        class="state"
      >
        {{ $t('home.load_error') }}
      </div>
      <ArticleList
        v-else
        :source="data"
        :load="loadPage"
      >
        <template #empty>
          <EmptyState
            v-if="selected"
            heading="h3"
            :title="$t('home.section_empty_title')"
          >
            {{ $t('home.section_empty_text') }}
          </EmptyState>
          <EmptyState
            v-else
            heading="h3"
            :title="$t('home.empty_title')"
          >
            {{ $t('home.empty_text') }}
          </EmptyState>
        </template>
      </ArticleList>
    </section>
    <section
      id="about"
      class="about wrap"
    >
      <div
        class="about-star"
        aria-hidden="true"
      >
        ✳
      </div><div>
        <p class="eyebrow caps">
          {{ $t('home.about_label') }}
        </p><h2>{{ $t('home.about_title_line1') }}<br><em>{{ $t('home.about_title_line2') }}</em></h2>
      </div><p class="about-copy">
        {{ $t('home.about_text') }}
      </p>
    </section>
  </main>
</template>
