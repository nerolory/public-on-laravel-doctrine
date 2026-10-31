<script setup lang="ts">
/** Кнопки «поделиться» — обычные ссылки, адреса готовит сервер; сторонние скрипты не загружаются. */
defineProps<{ links: { network: string, url: string }[] }>()
</script>

<template>
  <nav
    v-if="links.length"
    class="share-buttons"
    :aria-label="$t('share.label')"
  >
    <a
      v-for="link in links"
      :key="link.network"
      :href="link.url"
      class="share-button"
      :class="`share-${link.network}`"
      target="_blank"
      rel="noopener noreferrer nofollow"
    >
      {{ $t(`share.networks.${link.network}`) }}<span class="visually-hidden"> ({{ $t('share.new_window') }})</span>
    </a>
  </nav>
</template>
