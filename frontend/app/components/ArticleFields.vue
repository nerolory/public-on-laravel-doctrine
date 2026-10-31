<script setup lang="ts">
import type { ArticleFormData, SectionOption } from '~/composables/useArticles'

/** Поля формы статьи — общие для новой статьи и правки. */
const form = defineModel<ArticleFormData>({ required: true })
const { data: sections, error: sectionsError } = await useApiFetch<{ items: SectionOption[] }>(apiPaths.sections)
const limits = useFormLimits()
const uid = useId()
</script>

<template>
  <label>{{ $t('write.article_title') }}<input
    v-model="form.title"
    required
    :maxlength="limits.article_title"
  ></label>
  <CoverField v-model="form.cover" />
  <div class="editor-field">
    <span
      :id="`${uid}-content`"
      class="field-label"
    >{{ $t('write.content') }}</span>
    <ClientOnly>
      <RichEditor
        v-model="form.content"
        :limits="limits"
        :labelled-by="`${uid}-content`"
      />
      <template #fallback>
        <div
          class="editor-placeholder"
          aria-hidden="true"
        />
      </template>
    </ClientOnly>
  </div>
  <label>{{ $t('write.summary') }}
    <textarea
      v-model="form.summary"
      rows="3"
      :maxlength="limits.summary"
      class="article-input"
      :aria-describedby="`${uid}-summary`"
    />
    <small
      :id="`${uid}-summary`"
      class="field-hint"
    >{{ $t('write.summary_hint', { count: form.summary.length, max: limits.summary }) }}</small>
  </label>
  <TagInput
    v-model="form.tags"
    :label="$t('write.tags')"
    :max="limits.tags"
    :max-length="limits.tag_length"
  />
  <SectionMultiselect
    v-model="form.section_ids"
    :label="$t('write.sections')"
    :sections="sections?.items ?? []"
  />
  <FormMessage
    v-if="sectionsError"
    :text="$t('write.sections_error')"
    tone="error"
  />
  <PollEditor v-model="form.poll" />
</template>
