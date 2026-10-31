<script setup lang="ts">
/** Блок для гостя на страницах личного кабинета: просит войти в аккаунт. */
const { localePath } = useLocalization()
</script>

<template>
  <div class="form-card">
    <PageHeading :title="$t('common.login_required')" />
    <NuxtLink
      class="primary-button"
      :to="localePath('/login')"
    >
      {{ $t('nav.login') }} <span aria-hidden="true">↗</span>
    </NuxtLink>
  </div>
</template>
