<script setup lang="ts">
import type { UploadedImage } from '~~/editor/rich-editor'

/** Обложка статьи: загрузка с назначением cover, сервер сам делает нужные размеры. */
const cover = defineModel<UploadedImage | null>({ required: true })
const upload = useImageUpload()
const { busy, error, run } = useFormAction()
const uid = useId()

async function choose(event: Event) {
  if (!(event.target instanceof HTMLInputElement)) {
    return
  }

  const input = event.target
  const file = selectedFiles(input)[0]

  if (file) {
    await run(async () => {
      cover.value = await upload(file, 'cover')
    }, { failure: 'editor.upload_failed' })
  }

  input.value = ''
}
</script>

<template>
  <div
    class="cover-field"
    role="group"
    :aria-labelledby="`${uid}-label`"
  >
    <span
      :id="`${uid}-label`"
      class="field-label"
    >{{ $t('editor.cover') }}</span>
    <img
      v-if="cover"
      :src="cover.url"
      :srcset="cover.srcset"
      :sizes="IMAGE_SIZES.coverPreview"
      :width="cover.width"
      :height="cover.height"
      alt=""
      class="cover-preview"
    >
    <div class="cover-actions">
      <label class="file-button">{{
        busy ? $t('editor.uploading') : $t(cover ? 'editor.cover_replace' : 'editor.cover_choose')
      }}<input
        type="file"
        :accept="IMAGE_TYPES"
        class="visually-hidden"
        :disabled="busy"
        :aria-describedby="`${uid}-hint`"
        @change="choose"
      ></label>
      <button
        v-if="cover"
        type="button"
        class="text-button text-button-inline"
        @click="cover = null"
      >
        {{ $t('editor.cover_remove') }}
      </button>
    </div>
    <small
      :id="`${uid}-hint`"
      class="field-hint"
    >{{ $t('editor.cover_hint') }}</small>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
  </div>
</template>
