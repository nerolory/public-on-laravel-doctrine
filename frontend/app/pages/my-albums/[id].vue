<script setup lang="ts">
import type { AlbumView } from '~/composables/useArticles'

const route = useRoute()
const { localePath } = useLocalization()
const { t } = useI18n()
const isNew = computed(() => route.params.id === 'new')
useHead({ title: () => t(isNew.value ? 'albums.create' : 'albums.edit') })
const limits = useFormLimits()
const { data: album, error } = await useApiFetch<AlbumView>(() => apiPaths.album(String(route.params.id)), {
  immediate: !isNew.value,
})

async function saved() {
  await navigateTo({ path: localePath('/my-albums'), query: { saved: '1' } })
}
</script>

<template>
  <main class="auth-page wrap">
    <BackLink :to="localePath('/my-albums')">
      ← {{ $t('albums.mine') }}
    </BackLink>
    <div
      v-if="!isNew && error"
      class="state"
    >
      {{ $t(error.statusCode === 404 ? 'albums.not_found' : 'albums.load_error') }}
    </div>
    <div
      v-else-if="isNew || album"
      class="form-card"
    >
      <PageHeading
        :eyebrow="$t('albums.eyebrow')"
        :title="$t(isNew ? 'albums.create' : 'albums.edit')"
      />
      <RejectionNote
        v-if="!isNew && album?.rejection_reason"
        :reason="album.rejection_reason"
      />
      <AlbumForm
        v-if="isNew"
        :album="null"
        :max="limits.album_photos"
        @saved="saved"
      />
      <AlbumForm
        v-else-if="album"
        :album="album"
        :max="limits.album_photos"
        @saved="saved"
      />
    </div>
  </main>
</template>
