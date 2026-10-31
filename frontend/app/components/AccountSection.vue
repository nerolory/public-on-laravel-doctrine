<script setup lang="ts">
/**
 * Раздел кабинета. С заголовком — section с h2, связанным через aria-labelledby;
 * без заголовка — блок div той же вёрстки.
 */
const { title } = defineProps<{ title?: string }>()
const titleId = useId()
</script>

<template>
  <section
    v-if="title"
    class="account-section"
    :aria-labelledby="titleId"
  >
    <h2 :id="titleId">
      {{ title }}
    </h2>
    <slot />
  </section>
  <div
    v-else
    class="account-section"
  >
    <slot />
  </div>
</template>
