<script setup lang="ts">
/** Переключатель видимости поля профиля: галочка — видно всем, перечёркнутый круг — скрыто. */
defineProps<{ label: string }>()
const visible = defineModel<boolean>({ required: true })
const { t } = useI18n()
const state = computed(() => t(visible.value ? 'profile.visible' : 'profile.hidden'))
</script>

<template>
  <button
    type="button"
    class="visibility-toggle"
    :class="{ on: visible }"
    :aria-pressed="visible"
    :title="state"
    :aria-label="$t('profile.toggle', { field: label, state })"
    @click="visible = !visible"
  >
    <span aria-hidden="true">{{ visible ? '✓' : '⊘' }}</span>
  </button>
</template>
