<script setup lang="ts">
import type { PublicationStatus } from '#shared/types/content'

/**
 * Строка материала автора в кабинете: статус и мета-данные, содержимое слотом, причина отклонения и действия.
 * Удаление уходит событием delete: родитель решает, открыть окно архивации или удалить сразу.
 */
defineProps<{
  title: string
  status: PublicationStatus
  statusLabel: string
  to: string
  editTo: string
  editLabel: string
  rejectionReason: string | null
}>()
const emit = defineEmits<{ delete: [] }>()
</script>

<template>
  <li class="my-article">
    <p class="my-article-meta">
      <span
        class="status-badge"
        :class="`status-${status}`"
      >{{ statusLabel }}</span>
      <slot name="meta" />
    </p>
    <h2><NuxtLink :to="to">{{ title }}</NuxtLink></h2>
    <slot />
    <RejectionNote
      v-if="status === 'rejected' && rejectionReason"
      :reason="rejectionReason"
    />
    <div class="article-actions">
      <NuxtLink
        :to="editTo"
        class="text-link"
      >{{ editLabel }}<span class="visually-hidden">: {{ title }}</span></NuxtLink>
      <button
        type="button"
        class="text-button text-button-inline danger-text"
        @click="emit('delete')"
      >
        {{ $t('article.delete') }}<span class="visually-hidden">: {{ title }}</span>
      </button>
    </div>
  </li>
</template>
