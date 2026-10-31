<script setup lang="ts">
/** Пустое состояние списка: символ, заголовок и текст; текст может содержать ссылку через слот. */
defineProps<{ title: string, heading: 'h2' | 'h3' }>()
</script>

<template>
  <div class="empty">
    <span
      class="empty-symbol"
      aria-hidden="true"
    >✳</span><div>
      <component
        :is="heading"
      >
        {{ title }}
      </component>
      <p><slot /></p>
    </div>
  </div>
</template>
