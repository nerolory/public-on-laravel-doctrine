<script setup lang="ts">
/** Причина отклонения статьи или альбома, которую видит автор. */
defineProps<{ reason: string }>()
</script>

<template>
  <p class="rejection-note">
    <strong>{{ $t('article.rejection_reason') }}</strong> {{ reason }}
  </p>
</template>
