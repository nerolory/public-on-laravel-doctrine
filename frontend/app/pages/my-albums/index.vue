<script setup lang="ts">
import type { AlbumView } from '~/composables/useArticles'
import type { ListPage } from '#shared/types/content'

/** Сколько фото альбома показывать превью в списке: остальные видны на странице альбома. */
const THUMBNAILS_IN_LIST = 5

const route = useRoute()
const { localePath } = useLocalization()
const { submit } = useAccount()
const { notice, run } = useFormAction()
const { t } = useI18n()
useHead({ title: () => t('albums.mine') })
const page = usePageQuery()
const { data, error, refresh } = await useApiFetch<ListPage<AlbumView>>(apiPaths.myAlbums, {
  query: computed(() => ({ page: page.value })),
})
const savedNotice = computed(() => route.query.saved ? t('albums.saved') : '')

async function remove(album: AlbumView) {
  if (!confirm(t('albums.confirm_delete', { title: album.title }))) {
    return
  }

  const deleted = await run(() => submit(apiPaths.album(album.id), 'DELETE'), {
    success: 'albums.deleted',
    failure: 'albums.failed',
  })

  if (deleted) {
    await refresh()
  }
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
      {{ $t('albums.load_error') }}
    </div>
    <section
      v-else-if="data"
      class="account-layout"
    >
      <header class="account-head">
        <PageHeading
          :eyebrow="$t('my_articles.eyebrow')"
          :title="$t('albums.mine')"
        >
          <NuxtLink
            :to="localePath('/my-albums/new')"
            class="text-link"
          >{{ $t('albums.create') }} <span aria-hidden="true">↗</span></NuxtLink>
        </PageHeading>
      </header>
      <FormMessage
        v-if="notice || savedNotice"
        :text="notice ? notice.text : savedNotice"
        :tone="notice?.error ? 'error' : 'success'"
      />
      <EmptyState
        v-if="!data.items.length"
        heading="h2"
        :title="$t('albums.empty_title')"
      >
        {{ $t('albums.empty_text') }}
      </EmptyState>
      <ul
        v-else
        class="my-article-list"
      >
        <OwnedRow
          v-for="album in data.items"
          :key="album.id"
          :title="album.title"
          :status="album.status"
          :status-label="$t(`albums.status.${album.status}`)"
          :to="localePath(`/albums/${album.id}`)"
          :edit-to="localePath(`/my-albums/${album.id}`)"
          :edit-label="$t('article.edit')"
          :rejection-reason="album.rejection_reason"
          @delete="remove(album)"
        >
          <template #meta>
            <span>{{ $t('albums.photos', { count: album.items.length }) }}</span>
          </template>
          <div class="album-thumbs">
            <img
              v-for="item in album.items.slice(0, THUMBNAILS_IN_LIST)"
              :key="item.image.id"
              :src="item.image.url"
              :srcset="item.image.srcset"
              :sizes="IMAGE_SIZES.albumListThumb"
              width="96"
              height="72"
              :alt="item.alt ?? ''"
            >
          </div>
        </OwnedRow>
      </ul>
      <ListPagination
        v-if="page > 1 || data.has_more"
        :page="page"
        :has-more="data.has_more"
      />
    </section>
  </main>
</template>
