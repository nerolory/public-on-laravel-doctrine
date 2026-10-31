<script setup lang="ts">
import type { ReactionSummary, ReactionType } from '~/composables/useComments'

/** Реакции на статью или комментарий: три положительные и три отрицательные, можно поставить несколько. */
const props = defineProps<{ summary: ReactionSummary, endpoint: string, canReact: boolean }>()
const state = ref(props.summary)
watch(() => props.summary, (value) => {
  state.value = value
})
const { submit } = useAccount()
const { busy, error, run } = useFormAction()

/** Реакции в порядке показа: значок и признак отрицательной реакции для оформления. */
const REACTIONS: { type: ReactionType, icon: string, negative: boolean }[] = [
  { type: 'like', icon: '👍', negative: false },
  { type: 'love', icon: '❤️', negative: false },
  { type: 'insightful', icon: '💡', negative: false },
  { type: 'dislike', icon: '👎', negative: true },
  { type: 'angry', icon: '😡', negative: true },
  { type: 'boring', icon: '🥱', negative: true },
]

async function toggle(type: ReactionType) {
  if (!props.canReact || busy.value) {
    return
  }

  const method = state.value.mine.includes(type) ? 'DELETE' : 'PUT'

  await run(async () => {
    state.value = await submit<ReactionSummary>(`${props.endpoint}/${type}`, method)
  }, { failure: 'reactions.failed' })
}
</script>

<template>
  <div
    class="reaction-bar"
    role="group"
    :aria-label="$t('reactions.label')"
  >
    <button
      v-for="item in REACTIONS"
      :key="item.type"
      type="button"
      class="reaction"
      :class="{ mine: state.mine.includes(item.type), negative: item.negative }"
      :aria-pressed="state.mine.includes(item.type)"
      :aria-label="$t(`reactions.types.${item.type}`, { count: state.counts[item.type] })"
      :title="canReact ? $t(`reactions.names.${item.type}`) : $t('reactions.unavailable')"
      :disabled="!canReact || busy"
      @click="toggle(item.type)"
    >
      <span aria-hidden="true">{{ item.icon }}</span><span
        v-if="state.counts[item.type]"
        aria-hidden="true"
      >{{ state.counts[item.type] }}</span>
    </button>
    <span
      v-if="error"
      class="error"
      role="alert"
    >{{ error }}</span>
  </div>
</template>
