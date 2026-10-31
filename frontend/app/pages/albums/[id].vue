<script setup lang="ts">
import type { AlbumView } from '~/composables/useArticles'

type PublicAlbum = AlbumView & { owner: { id: number, name: string } | null, permissions: { edit: boolean } }
const route = useRoute()
const { localePath } = useLocalization()
const { t } = useI18n()
const { data: album, error } = await useApiFetch<PublicAlbum>(() => apiPaths.album(String(route.params.id)))
useHead({ title: () => album.value?.title ?? t('albums.not_found') })
</script>

<template>
  <main class="wrap reading">
    <BackLink :to="localePath('/')">
      {{ $t('nav.back_home') }}
    </BackLink>
    <div
      v-if="error"
      class="state"
    >
      {{ $t('albums.not_found') }}
    </div>
    <article v-else-if="album">
      <StatusBanner
        v-if="album.status !== 'published'"
        :status="album.status"
        :label="$t(`albums.status.${album.status}`)"
        :rejection-reason="album.rejection_reason"
      />
      <p class="eyebrow caps">
        {{ $t('albums.eyebrow') }}
      </p>
      <h1>{{ album.title }}</h1>
      <p class="article-meta">
        <NuxtLink
          v-if="album.owner"
          :to="localePath(`/authors/${album.owner.id}`)"
          class="text-link"
        >{{ album.owner.name }}</NuxtLink>
        <span>{{ $t('albums.photos', { count: album.items.length }) }}</span>
        <NuxtLink
          v-if="album.permissions.edit"
          :to="localePath(`/my-albums/${album.id}`)"
          class="text-link"
        >{{ $t('article.edit') }}</NuxtLink>
        <ComplaintButton
          v-if="album.status === 'published' && !album.permissions.edit"
          :id="album.id"
          target="album"
        />
      </p>
      <div class="album-page-grid">
        <figure
          v-for="item in album.items"
          :key="item.image.id"
        >
          <img
            :src="item.image.url"
            :srcset="item.image.srcset"
            :sizes="IMAGE_SIZES.albumPhoto"
            :width="item.image.width"
            :height="item.image.height"
            :alt="item.alt ?? ''"
            loading="lazy"
          >
          <figcaption v-if="item.alt">
            {{ item.alt }}
          </figcaption>
        </figure>
      </div>
    </article>
  </main>
</template>
