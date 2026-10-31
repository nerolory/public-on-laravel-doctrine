<script setup lang="ts">
import type { ArticleListItem } from '~/composables/useArticles'
import type { ImageView, ListPage } from '#shared/types/content'

type Author = {
  id: number
  name: string
  avatar: ImageView | null
  birth_date: string | null
  gender: string | null
  about: string | null
  contacts: Record<string, string>
  socials: Record<string, string>
  articles: ListPage<ArticleListItem>
}

const route = useRoute()
const { localePath, locale } = useLocalization()
const { t } = useI18n()
const { get } = useApi()
const authorId = computed(() => String(route.params.id))
const { data: author, error } = await useApiFetch<Author>(() => apiPaths.authors(authorId.value))
useHead({ title: () => author.value ? author.value.name : t('author.not_found') })
const birthDate = computed(() => author.value?.birth_date ? formatDate(author.value.birth_date, locale.value) : '')
// Страницы статей приходят вместе с профилем: следующая страница запрашивается тем же адресом с номером страницы.
const loadArticles = async (page: number) => (await get<Author>(apiPaths.authors(authorId.value), { page })).articles
</script>

<template>
  <main class="wrap reading author-page">
    <BackLink :to="localePath('/#articles')">
      {{ $t('nav.all_articles') }}
    </BackLink>
    <div
      v-if="error"
      class="state"
    >
      {{ $t(error.statusCode === 404 ? 'author.not_found' : 'author.load_error') }}
    </div>
    <article v-else-if="author">
      <header class="author-head">
        <img
          v-if="author.avatar"
          class="avatar-large"
          :src="author.avatar.url"
          :srcset="author.avatar.srcset"
          :sizes="IMAGE_SIZES.avatarLarge"
          width="96"
          height="96"
          :alt="$t('avatar.alt')"
        >
        <div>
          <p class="eyebrow caps">
            {{ $t('author.eyebrow') }}
          </p>
          <h1>{{ author.name }}</h1>
          <ComplaintButton
            :id="author.id"
            target="user"
          />
          <p
            v-if="birthDate"
            class="form-intro"
          >
            {{ $t('author.born', { date: birthDate }) }}
          </p>
          <p
            v-if="author.gender"
            class="form-intro"
          >
            {{ $t(`profile.gender_${author.gender}`) }}
          </p>
        </div>
      </header>
      <p
        v-if="author.about"
        class="reading-content"
      >
        {{ author.about }}
      </p>
      <ul
        v-if="Object.keys(author.contacts).length || Object.keys(author.socials).length"
        class="author-links"
      >
        <li v-if="author.contacts.phone">
          <a :href="`tel:${author.contacts.phone.replace(/[^+0-9]/g, '')}`">{{ author.contacts.phone }}</a>
        </li>
        <li v-if="author.contacts.email">
          <a :href="`mailto:${author.contacts.email}`">{{ author.contacts.email }}</a>
        </li>
        <li
          v-for="(url, network) in author.socials"
          :key="network"
        >
          <a
            :href="url"
            rel="me nofollow noopener"
            target="_blank"
          >{{ $t(`profile.networks.${network}`) }}<span
            class="visually-hidden"
          >, {{ $t('share.new_window') }}</span></a>
        </li>
      </ul>
      <section
        class="author-articles"
        aria-labelledby="author-articles-title"
      >
        <h2 id="author-articles-title">
          {{ $t('author.articles') }}
        </h2>
        <ArticleList
          :source="author.articles"
          :load="loadArticles"
        >
          <template #empty>
            <p class="state">
              {{ $t('author.no_articles') }}
            </p>
          </template>
        </ArticleList>
      </section>
    </article>
  </main>
</template>
