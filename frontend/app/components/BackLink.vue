<script setup lang="ts">
/** Ссылка «назад» в начале страницы: адрес задаёт родитель, подпись идёт слотом. */
defineProps<{ to: string }>()
</script>

<template>
  <NuxtLink
    :to="to"
    class="back-link"
  >
    <slot />
  </NuxtLink>
</template>
