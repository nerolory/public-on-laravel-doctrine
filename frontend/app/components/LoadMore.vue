<script setup lang="ts">
/** Кнопка «Показать ещё» для списков с подгрузкой: состояние загрузки и текст ошибки приходят от родителя. */
const { busy, error } = defineProps<{ busy: boolean, error: string }>()
const emit = defineEmits<{ more: [] }>()
</script>

<template>
  <div
    class="pagination"
    aria-live="polite"
  >
    <span
      v-if="error"
      class="error"
    >{{ error }}</span><button
      :disabled="busy"
      @click="emit('more')"
    >
      {{ busy ? $t('home.more_loading') : error ? $t('home.more_retry') : $t('home.more') }}
    </button>
  </div>
</template>
