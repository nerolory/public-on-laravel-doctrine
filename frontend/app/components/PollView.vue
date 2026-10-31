<script setup lang="ts">
import type { ListPage } from '#shared/types/content'
import type { PollOptionView, PollViewData, PollVoter } from '~/composables/useArticles'

/** Опрос на странице статьи: результаты видны всем, голосуют зарегистрированные. Все правила проверяет сервер. */
const props = defineProps<{ poll: PollViewData }>()
const state = ref(props.poll)
watch(() => props.poll, (value) => {
  state.value = value
})
const { user, submit } = useAccount()
const { get } = useApi()
const { localePath, locale } = useLocalization()
const { busy, error, run } = useFormAction()
const uid = useId()

const editing = ref(false)
const choice = ref<number[]>([])
const showForm = computed(() => state.value.can_vote && (state.value.my_choice === null || editing.value))

/** Вариант, чьи проголосовавшие открыты в окне. */
const votersOption = ref<PollOptionView | null>(null)
const {
  items: voters,
  hasMore: votersHasMore,
  busy: votersBusy,
  error: votersError,
  more: moreVoters,
  restart: restartVoters,
  reset: resetVoters,
} = usePagedList<PollVoter>(
  () => null,
  (page) => {
    const option = votersOption.value

    if (!option) {
      throw new Error('Вариант для списка проголосовавших не выбран')
    }

    return get<ListPage<PollVoter>>(apiPaths.pollVoters(state.value.id, option.id), { page })
  },
  'poll.voters_failed',
)
const { dialog: votersDialog, open: openVoters, close: closeVoters } = useFormDialog(resetVoters)

function startEditing() {
  choice.value = [...(state.value.my_choice ?? [])]
  editing.value = true
}

async function send(method: 'POST' | 'DELETE') {
  const body = method === 'POST' ? { options: choice.value } : undefined
  const sent = await run(async () => {
    state.value = await submit<PollViewData>(apiPaths.pollVotes(state.value.id), method, body)
  }, { failure: 'poll.failed' })

  if (sent) {
    editing.value = false
    choice.value = []
  }
}

/** Открывает окно с проголосовавшими за вариант; первая страница загружается при каждом открытии. */
async function showVoters(option: PollOptionView) {
  votersOption.value = option
  openVoters()
  await restartVoters()
}
</script>

<template>
  <section
    class="poll"
    :aria-labelledby="`${uid}-question`"
  >
    <p class="eyebrow caps">
      {{ $t('poll.label') }}
    </p>
    <h2 :id="`${uid}-question`">
      {{ state.question }}
    </h2>
    <p class="poll-meta">
      <span>{{ $t('poll.voters', { count: state.total_voters }) }}</span>
      <span v-if="state.closed">{{ $t('poll.closed') }}</span>
      <span v-if="!state.closed && state.ends_at">
        {{ $t('poll.until') }}
        <NuxtTime
          :datetime="state.ends_at"
          :locale="locale"
          date-style="long"
          time-style="short"
        />
      </span>
      <span v-if="state.multiple">{{ $t('poll.choose_up_to', { count: state.max_choices }) }}</span>
    </p>
    <form
      v-if="showForm"
      class="poll-form"
      @submit.prevent="send('POST')"
    >
      <fieldset>
        <legend class="visually-hidden">
          {{ state.question }}
        </legend>
        <label
          v-for="option in state.options"
          :key="option.id"
          class="poll-choice"
        >
          <input
            v-model="choice"
            :type="state.multiple ? 'checkbox' : 'radio'"
            :name="`${uid}-choice`"
            :value="option.id"
            :disabled="state.multiple && choice.length >= state.max_choices && !choice.includes(option.id)"
          >
          <span>{{ option.text }}</span>
        </label>
      </fieldset>
      <div class="poll-actions">
        <button
          type="submit"
          class="primary-button poll-submit"
          :disabled="busy || !choice.length"
        >
          {{ $t('poll.vote') }}
        </button>
        <button
          v-if="editing"
          type="button"
          class="text-button text-button-inline"
          @click="editing = false"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </form>
    <ul class="poll-results">
      <li
        v-for="option in state.options"
        :key="option.id"
        :class="{ mine: state.my_choice?.includes(option.id) }"
      >
        <div class="poll-result-head">
          <span>{{ option.text }}<span
            v-if="state.my_choice?.includes(option.id)"
            class="poll-mine"
          > · {{ $t('poll.your_choice') }}</span></span>
          <span>{{ option.percent }}% <small>({{ option.votes }})</small></span>
        </div>
        <div
          class="poll-bar"
          role="progressbar"
          :aria-valuenow="option.percent"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="option.text"
        >
          <span :style="{ width: `${option.percent}%` }" />
        </div>
        <div
          v-if="!state.anonymous && option.voters.length"
          class="poll-voters"
        >
          <NuxtLink
            v-for="voter in option.voters"
            :key="voter.id"
            :to="localePath(`/authors/${voter.id}`)"
            class="poll-voter"
            :title="voter.name"
          >
            <img
              v-if="voter.avatar"
              :src="voter.avatar"
              width="28"
              height="28"
              :alt="voter.name"
            >
            <span
              v-else
              role="img"
              :aria-label="voter.name"
            >{{ initialOf(voter.name) }}</span>
          </NuxtLink>
          <button
            v-if="option.more"
            type="button"
            class="text-button"
            @click="showVoters(option)"
          >
            {{ $t('poll.more_voters', { count: option.more }) }}
          </button>
        </div>
      </li>
    </ul>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
    <div class="poll-actions">
      <template v-if="state.my_choice && state.allow_revote && state.can_vote && !editing">
        <button
          type="button"
          class="text-button text-button-inline"
          @click="startEditing"
        >
          {{ $t('poll.change') }}
        </button>
        <button
          type="button"
          class="text-button text-button-inline"
          :disabled="busy"
          @click="send('DELETE')"
        >
          {{ $t('poll.retract') }}
        </button>
      </template>
      <p
        v-if="!user && !state.closed"
        class="field-hint"
      >
        <NuxtLink
          :to="localePath('/login')"
          class="text-link"
        >{{ $t('nav.login') }}</NuxtLink>, {{ $t('poll.login_to_vote') }}
      </p>
    </div>
    <AppDialog
      ref="votersDialog"
      v-slot="{ titleId }"
    >
      <h2 :id="titleId">
        {{ votersOption ? votersOption.text : $t('poll.voters_title') }}
      </h2>
      <ul class="poll-voter-list">
        <li
          v-for="voter in voters"
          :key="voter.id"
        >
          <NuxtLink :to="localePath(`/authors/${voter.id}`)">{{ voter.name }}</NuxtLink>
        </li>
      </ul>
      <LoadMore
        v-if="votersHasMore"
        :busy="votersBusy"
        :error="votersError"
        @more="moreVoters"
      />
      <div class="dialog-actions">
        <button
          type="button"
          class="text-button"
          @click="closeVoters()"
        >
          {{ $t('poll.close') }}
        </button>
      </div>
    </AppDialog>
  </section>
</template>
