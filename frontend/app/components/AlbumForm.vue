<script setup lang="ts">
import type { AlbumItem, AlbumView } from '~/composables/useArticles'

/** Создание и правка альбома: фото загружаются сразу (назначение album), порядок и подписи — до сохранения. */
const props = defineProps<{ album: AlbumView | null, max: number }>()
const emit = defineEmits<{ saved: [] }>()
const { submit } = useAccount()
const upload = useImageUpload()
const limits = useFormLimits()
const { busy: uploading, error: uploadError, run: runUpload } = useFormAction()
const { busy: saving, error: saveError, run: runSave } = useFormAction()
const title = ref(props.album?.title ?? '')
const items = ref<AlbumItem[]>(props.album ? props.album.items.map(item => ({ ...item })) : [])
const uid = useId()
const error = computed(() => uploadError.value || saveError.value)

async function add(event: Event) {
  if (!(event.target instanceof HTMLInputElement)) {
    return
  }

  const input = event.target
  const files = selectedFiles(input).slice(0, props.max - items.value.length)

  await runUpload(async () => {
    for (const file of files) {
      items.value.push({ image: await upload(file, 'album'), alt: '' })
    }
  }, { failure: 'editor.upload_failed' })
  input.value = ''
}

/** Меняет фото местами с соседним; у крайних фото кнопка сдвига за край недоступна. */
function move(index: number, shift: -1 | 1) {
  const current = items.value[index]
  const neighbour = items.value[index + shift]

  if (!current || !neighbour) {
    return
  }

  const list = [...items.value]

  list[index] = neighbour
  list[index + shift] = current
  items.value = list
}

async function save() {
  const body = { title: title.value, items: items.value.map(item => ({ image: item.image.id, alt: item.alt })) }
  const saved = await runSave(
    () => props.album ? submit(apiPaths.album(props.album.id), 'PUT', body) : submit(apiPaths.albums, 'POST', body),
    { failure: 'albums.failed' },
  )

  if (saved) {
    emit('saved')
  }
}
</script>

<template>
  <form
    class="album-form"
    @submit.prevent="save"
  >
    <label>{{ $t('albums.title_field') }}<input
      v-model="title"
      required
      :maxlength="limits.album_title"
    ></label>
    <p class="field-hint">
      {{ $t('albums.photos_hint', { count: items.length, max }) }}
    </p>
    <ol
      v-if="items.length"
      class="album-form-items"
    >
      <li
        v-for="(item, index) in items"
        :key="item.image.id"
      >
        <img
          :src="item.image.url"
          :srcset="item.image.srcset"
          :sizes="IMAGE_SIZES.albumFormThumb"
          width="120"
          height="90"
          alt=""
        >
        <label class="album-form-alt">{{ $t('albums.alt', { number: index + 1 }) }}<input
          v-model="item.alt"
          :maxlength="limits.image_alt"
        ></label>
        <div class="album-form-tools">
          <button
            type="button"
            :disabled="index === 0"
            :aria-label="$t('albums.move_up', { number: index + 1 })"
            @click="move(index, -1)"
          >
            <span aria-hidden="true">↑</span>
          </button>
          <button
            type="button"
            :disabled="index === items.length - 1"
            :aria-label="$t('albums.move_down', { number: index + 1 })"
            @click="move(index, 1)"
          >
            <span aria-hidden="true">↓</span>
          </button>
          <button
            type="button"
            class="danger-text"
            :aria-label="$t('albums.remove', { number: index + 1 })"
            @click="items.splice(index, 1)"
          >
            <span aria-hidden="true">×</span>
          </button>
        </div>
      </li>
    </ol>
    <label
      v-if="items.length < max"
      class="file-button"
    >{{ uploading ? $t('editor.uploading') : $t('albums.add_photos') }}<input
      type="file"
      multiple
      :accept="IMAGE_TYPES"
      class="visually-hidden"
      :disabled="uploading"
      :aria-describedby="`${uid}-hint`"
      @change="add"
    ></label>
    <small
      :id="`${uid}-hint`"
      class="field-hint"
    >{{ $t('albums.moderation_hint') }}</small>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
    <button
      type="submit"
      class="primary-button"
      :disabled="saving || uploading || !items.length"
    >
      {{ $t('albums.save') }} <span aria-hidden="true">↗</span>
    </button>
  </form>
</template>
