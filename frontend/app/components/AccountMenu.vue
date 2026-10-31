<script setup lang="ts">
/** Меню под аватаром: ссылки на кабинет, свои материалы, админку и выход. */
const { user, leave, leaving } = useAccount()
const { localePath } = useLocalization()
const { busy, error } = leaving
const open = ref(false)
const root = ref<HTMLElement | null>(null)
const button = ref<HTMLButtonElement | null>(null)
const uid = useId()
const route = useRoute()
const initials = computed(() => user.value ? initialOf(user.value.public_name) : '')

useOutsideClick(root, () => {
  open.value = false
})
watch(() => route.fullPath, () => {
  open.value = false
})

/** Выход из меню: после выхода открывается главная, меню закрывается. */
async function signOut() {
  if (await leave(localePath('/'))) {
    open.value = false
  }
}
</script>

<template>
  <div
    v-if="user"
    ref="root"
    class="account-menu"
    @keydown.esc="open = false; button?.focus()"
  >
    <button
      ref="button"
      type="button"
      class="avatar-button"
      :aria-expanded="open"
      :aria-controls="`${uid}-menu`"
      :aria-label="$t('menu.open')"
      @click="open = !open"
    >
      <img
        v-if="user.avatar"
        :src="user.avatar.url"
        :srcset="user.avatar.srcset"
        :sizes="IMAGE_SIZES.avatarMedium"
        width="40"
        height="40"
        :alt="$t('avatar.alt')"
      >
      <span
        v-else
        aria-hidden="true"
      >{{ initials }}</span>
    </button>
    <div
      v-if="open"
      :id="`${uid}-menu`"
      class="account-dropdown"
    >
      <p class="account-dropdown-name">
        {{ user.name }}
      </p>
      <NuxtLink :to="localePath('/account')">{{ $t('menu.account') }}</NuxtLink>
      <NuxtLink :to="localePath('/my-articles')">{{ $t('menu.my_articles') }}</NuxtLink>
      <NuxtLink :to="localePath('/my-albums')">{{ $t('menu.my_albums') }}</NuxtLink>
      <NuxtLink :to="localePath('/write')">{{ $t('menu.write') }}</NuxtLink>
      <a
        v-if="user.capabilities.admin"
        :href="adminPaths.home"
      >{{ $t('menu.admin') }}</a>
      <button
        type="button"
        :disabled="busy"
        @click="signOut"
      >
        {{ $t('menu.logout') }}
      </button>
      <FormMessage
        v-if="error"
        :text="error"
        tone="error"
      />
    </div>
  </div>
</template>
