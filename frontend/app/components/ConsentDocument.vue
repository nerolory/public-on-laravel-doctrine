<script setup lang="ts">
/** Текст документа согласия: раскрывающийся блок с заголовком и текстом. */
defineProps<{ summary: string, text: string, open?: boolean }>()
</script>

<template>
  <details
    class="consent-text"
    :open="open"
  >
    <summary>{{ summary }}</summary>
    <p>{{ text }}</p>
  </details>
</template>
