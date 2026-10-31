<script setup lang="ts">
/** Сообщение под формой: ошибка объявляется диктору как alert, успех как status. Дополнительная ссылка идёт слотом. */
defineProps<{ text: string, tone: 'error' | 'success' }>()
</script>

<template>
  <p
    :class="tone"
    :role="tone === 'error' ? 'alert' : 'status'"
  >
    {{ text }} <slot />
  </p>
</template>
