<script setup lang="ts">
import type { SectionOption } from '~/composables/useArticles'

/** Выбор нескольких разделов из дерева: поиск по названию, выбранные — плашками. */
const props = defineProps<{ sections: SectionOption[], label: string }>()
const selected = defineModel<number[]>({ required: true })
const open = ref(false)
const query = ref('')
const root = ref<HTMLElement | null>(null)
const search = ref<HTMLInputElement | null>(null)
const toggleButton = ref<HTMLButtonElement | null>(null)
const uid = useId()

const byId = computed(() => new Map(props.sections.map(section => [section.id, section])))
const chosen = computed(() =>
  selected.value
    .map(id => byId.value.get(id))
    .filter((section): section is SectionOption => !!section),
)
const visible = computed(() => {
  const term = query.value.trim().toLocaleLowerCase()

  return term ? props.sections.filter(section => section.name.toLocaleLowerCase().includes(term)) : props.sections
})

function toggle(id: number) {
  selected.value = selected.value.includes(id) ? selected.value.filter(item => item !== id) : [...selected.value, id]
}
async function setOpen(value: boolean) {
  open.value = value

  if (value) {
    await nextTick()
    search.value?.focus()
  } else {
    query.value = ''
  }
}
useOutsideClick(root, () => {
  if (open.value) {
    setOpen(false)
  }
})
</script>

<template>
  <div
    ref="root"
    class="multiselect"
    @keydown.esc.stop="setOpen(false); toggleButton?.focus()"
  >
    <span
      :id="`${uid}-label`"
      class="multiselect-label"
    >{{ label }}</span>
    <div
      class="multiselect-control"
      :class="{ open }"
      @click="setOpen(!open)"
    >
      <span
        v-if="!chosen.length"
        class="multiselect-placeholder"
      >{{ $t('multiselect.empty') }}</span>
      <span
        v-for="section in chosen"
        :key="section.id"
        class="multiselect-chip"
      >{{ section.name }}<button
        type="button"
        :aria-label="$t('multiselect.remove', { name: section.name })"
        @click.stop="toggle(section.id)"
      >×</button></span>
      <button
        ref="toggleButton"
        type="button"
        class="multiselect-toggle"
        :aria-expanded="open"
        :aria-controls="`${uid}-panel`"
        :aria-labelledby="`${uid}-label`"
        @click.stop="setOpen(!open)"
      >
        <span aria-hidden="true">{{ open ? '▴' : '▾' }}</span>
      </button>
    </div>
    <div
      v-if="open"
      :id="`${uid}-panel`"
      class="multiselect-panel"
    >
      <input
        ref="search"
        v-model="query"
        type="search"
        class="multiselect-search"
        :placeholder="$t('multiselect.search')"
        :aria-label="$t('multiselect.search')"
      >
      <div
        role="group"
        :aria-labelledby="`${uid}-label`"
        class="multiselect-options"
      >
        <label
          v-for="section in visible"
          :key="section.id"
          class="multiselect-option"
          :class="{ root: section.level === 0 }"
          :style="{ '--level': query ? 0 : section.level }"
        >
          <input
            type="checkbox"
            :checked="selected.includes(section.id)"
            @change="toggle(section.id)"
          >
          <span>{{ section.name }}</span>
        </label>
        <p
          v-if="!visible.length"
          class="multiselect-empty"
        >
          {{ $t('multiselect.nothing') }}
        </p>
      </div>
    </div>
  </div>
</template>
