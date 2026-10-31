<script setup lang="ts">
/**
 * Заголовок страницы: надпись над заголовком первого уровня с точкой.
 * Ссылки и сообщения, идущие за заголовком, — слотом.
 */
defineProps<{ eyebrow?: string, title: string }>()
</script>

<template>
  <p
    v-if="eyebrow"
    class="eyebrow caps"
  >
    {{ eyebrow }}
  </p>
  <h1>{{ title }}<span class="period">.</span></h1>
  <slot />
</template>
