<script setup lang="ts">
/** Строка анкеты: поля слева и переключатель видимости этих полей справа. */
const { label } = defineProps<{ label: string }>()
const visible = defineModel<boolean>({ required: true })
</script>

<template>
  <div class="profile-row">
    <slot />
    <VisibilityToggle
      v-model="visible"
      :label="label"
    />
  </div>
</template>
