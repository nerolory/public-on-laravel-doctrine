<script setup lang="ts">
/** Теги статьи: существующие подсказывает сервер (datalist), новые добавляются по Enter или запятой. */
const props = defineProps<{ label: string, max: number, maxLength: number }>()
const tags = defineModel<string[]>({ required: true })
const { get } = useApi()
const draft = ref('')
const suggestions = ref<string[]>([])
const suggestFailed = ref(false)
const uid = useId()

/** Подсказки запрашиваются с двух символов: по одному совпадений слишком много. */
const SUGGEST_FROM = 2

/** Пауза после ввода в миллисекундах: запрос уходит, когда автор перестал печатать. */
const SUGGEST_DELAY = 250

let timer: ReturnType<typeof setTimeout> | undefined
watch(draft, (value) => {
  clearTimeout(timer)
  const term = value.trim()

  if (term.length < SUGGEST_FROM) {
    suggestions.value = []

    return
  }

  timer = setTimeout(() => suggest(term), SUGGEST_DELAY)
})
onBeforeUnmount(() => clearTimeout(timer))

/** Подсказки для введённого текста; ответ на устаревший ввод отбрасывается. */
async function suggest(term: string) {
  try {
    const { items } = await get<{ items: string[] }>(apiPaths.tags, { q: term })

    suggestFailed.value = false

    if (draft.value.trim() === term) {
      suggestions.value = items
    }
  } catch {
    suggestFailed.value = true
    suggestions.value = []
  }
}

/** Список собирается целиком и присваивается один раз: модель обновится только после перерисовки родителя. */
function add() {
  const next = [...tags.value]

  for (const part of draft.value.split(',')) {
    const name = part.trim().replace(/\s+/g, ' ')
    const duplicate = next.some(tag => tag.toLocaleLowerCase() === name.toLocaleLowerCase())

    if (name && !duplicate && next.length < props.max) {
      next.push(name)
    }
  }

  if (next.length !== tags.value.length) {
    tags.value = next
  }

  draft.value = ''
}

function remove(name: string) {
  tags.value = tags.value.filter(tag => tag !== name)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault()
    add()

    return
  }

  const last = tags.value.at(-1)

  if (event.key === 'Backspace' && !draft.value && last !== undefined) {
    remove(last)
  }
}
</script>

<template>
  <div class="tag-input">
    <label :for="`${uid}-input`">{{ label }}</label>
    <ul
      v-if="tags.length"
      class="tag-chips"
      :aria-label="label"
    >
      <li
        v-for="tag in tags"
        :key="tag"
        class="tag-chip"
      >
        {{ tag }}
        <button
          type="button"
          :aria-label="$t('tags.remove', { name: tag })"
          @click="remove(tag)"
        >
          <span aria-hidden="true">×</span>
        </button>
      </li>
    </ul>
    <input
      :id="`${uid}-input`"
      v-model="draft"
      :list="`${uid}-list`"
      :maxlength="maxLength"
      autocomplete="off"
      :disabled="tags.length >= max"
      :aria-describedby="`${uid}-hint`"
      @keydown="onKeydown"
      @change="add"
    >
    <datalist :id="`${uid}-list`">
      <option
        v-for="name in suggestions"
        :key="name"
        :value="name"
      />
    </datalist>
    <small
      :id="`${uid}-hint`"
      class="field-hint"
    >{{ $t('tags.hint', { count: tags.length, max }) }}</small>
    <small
      v-if="suggestFailed"
      class="error"
      role="status"
    >{{ $t('tags.suggest_failed') }}</small>
  </div>
</template>
