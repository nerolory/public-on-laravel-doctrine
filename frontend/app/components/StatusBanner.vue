<script setup lang="ts">
import type { PublicationStatus } from '#shared/types/content'

/** Баннер состояния модерации над статьёй или альбомом: надпись, пояснение для автора и причина отклонения. */
defineProps<{ status: PublicationStatus, label: string, rejectionReason: string | null, note?: string }>()
</script>

<template>
  <div
    class="status-banner"
    :class="`status-${status}`"
  >
    <p>
      <span
        class="status-badge"
        :class="`status-${status}`"
      >{{ label }}</span><template v-if="note">
        {{ note }}
      </template>
    </p>
    <RejectionNote
      v-if="rejectionReason"
      :reason="rejectionReason"
    />
  </div>
</template>
