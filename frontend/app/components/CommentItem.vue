<script setup lang="ts">
import type { ListPage } from '#shared/types/content'
import type { CommentView } from '~/composables/useComments'

/** Комментарий или ответ. Ответы показываются одним уровнем под корнем ветки. */
const props = defineProps<{ comment: CommentView, articleId: number, rootId?: number }>()
const emit = defineEmits<{ replied: [comment: CommentView] }>()
const state = ref(props.comment)
watch(() => props.comment, (value) => {
  state.value = value
})
const { submit } = useAccount()
const { get } = useApi()
const { localePath, locale } = useLocalization()
const { t } = useI18n()
const { busy, error, run, reset } = useFormAction()

const isRoot = computed(() => props.rootId === undefined)
/** Подпись ответа: у комментария удалённого автора имени нет. */
const replyLabel = computed(() => state.value.author
  ? t('comments.reply_to', { name: state.value.author.name })
  : t('comments.reply_to_removed'))
const mode = ref<'view' | 'edit' | 'reply'>('view')
const draft = ref('')

const repliesOpen = ref(false)

/** Ответы ветки: первая страница загружается при открытии, остальные — кнопкой «Ещё». */
const {
  items: replies,
  hasMore: repliesMore,
  busy: repliesBusy,
  error: repliesError,
  more: moreReplies,
  restart: restartReplies,
  append: appendReply,
} = usePagedList<CommentView>(
  () => null,
  page => get<ListPage<CommentView>>(apiPaths.commentReplies(state.value.id), { page }),
  'comments.load_failed',
)

/** Права удалённого комментария: заглушка без действий. */
const NO_PERMISSIONS = { edit: false, delete: false, reply: false, react: false }

/** Открывает ответы; при неудаче ветка остаётся закрытой, а ошибка показывается у кнопки. */
async function openReplies() {
  repliesOpen.value = await restartReplies()
}

function start(next: 'edit' | 'reply') {
  draft.value = next === 'edit' ? state.value.body ?? '' : ''
  reset()
  mode.value = next
}

async function save() {
  const saved = await run(async () => {
    if (mode.value === 'edit') {
      state.value = await submit<CommentView>(apiPaths.comment(state.value.id), 'PUT', { body: draft.value })

      return
    }

    addReply(await submit<CommentView>(
      apiPaths.articleComments(props.articleId),
      'POST',
      { body: draft.value, reply_to: state.value.id },
    ))
  }, { failure: 'comments.failed' })

  if (saved) {
    mode.value = 'view'
  }
}

async function remove() {
  if (!confirm(t('comments.confirm_delete'))) {
    return
  }

  await run(async () => {
    await submit(apiPaths.comment(state.value.id), 'DELETE')
    state.value = { ...state.value, state: 'deleted', body: null, author: null, permissions: NO_PERMISSIONS }
  }, { failure: 'comments.failed' })
}

/** Ответ на ответ попадает в ту же ветку: корень добавляет его в свой список. */
function addReply(reply: CommentView) {
  if (!isRoot.value) {
    emit('replied', reply)

    return
  }

  appendReply(reply)
  repliesOpen.value = true
  state.value = { ...state.value, reply_count: state.value.reply_count + 1 }
}
</script>

<template>
  <article
    class="comment"
    :class="{ reply: !isRoot }"
  >
    <div
      class="comment-avatar"
      aria-hidden="true"
    >
      <img
        v-if="state.author?.avatar"
        :src="state.author.avatar"
        width="40"
        height="40"
        alt=""
      >
      <span v-else-if="state.author">{{ initialOf(state.author.name) }}</span>
    </div>
    <div class="comment-main">
      <header class="comment-head">
        <h3 class="comment-author">
          <NuxtLink
            v-if="state.author"
            :to="localePath(`/authors/${state.author.id}`)"
          >{{ state.author.name }}</NuxtLink>
          <span
            v-else
            class="muted"
          >{{ $t('comments.removed_author') }}</span>
        </h3>
        <NuxtTime
          :datetime="state.created_at"
          :locale="locale"
          date-style="medium"
          time-style="short"
        />
        <span
          v-if="state.edited"
          class="muted"
        >{{ $t('comments.edited') }}</span>
      </header>
      <p
        v-if="state.state !== 'visible'"
        class="comment-body muted"
      >
        {{ $t(`comments.${state.state}`) }}
      </p>
      <CommentForm
        v-else-if="mode === 'edit'"
        :id="`edit-${state.id}`"
        v-model="draft"
        :label="$t('comments.edit')"
        :submit-text="$t('comments.save')"
        :busy="busy"
        :rows="3"
        cancelable
        @submit="save"
        @cancel="mode = 'view'"
      />
      <p
        v-else
        class="comment-body"
      >
        <NuxtLink
          v-if="state.reply_to"
          :to="localePath(`/authors/${state.reply_to.id}`)"
          class="comment-mention"
        >@{{ state.reply_to.name }}</NuxtLink> {{ state.body }}
      </p>
      <div
        v-if="state.state === 'visible' && mode !== 'edit'"
        class="comment-actions"
      >
        <ReactionBar
          :summary="state.reactions"
          :endpoint="apiPaths.commentReactions(state.id)"
          :can-react="state.permissions.react"
        />
        <button
          v-if="state.permissions.reply"
          type="button"
          class="text-button text-button-inline"
          @click="start('reply')"
        >
          {{ $t('comments.reply') }}
        </button>
        <button
          v-if="state.permissions.edit"
          type="button"
          class="text-button text-button-inline"
          @click="start('edit')"
        >
          {{ $t('comments.edit') }}
        </button>
        <button
          v-if="state.permissions.delete"
          type="button"
          class="text-button text-button-inline danger-text"
          @click="remove"
        >
          {{ $t('comments.delete') }}
        </button>
        <ComplaintButton
          v-if="!state.permissions.edit"
          :id="state.id"
          target="comment"
        />
      </div>
      <CommentForm
        v-if="mode === 'reply'"
        :id="`reply-${state.id}`"
        v-model="draft"
        :label="replyLabel"
        :placeholder="replyLabel"
        :submit-text="$t('comments.send')"
        :busy="busy"
        :rows="2"
        cancelable
        @submit="save"
        @cancel="mode = 'view'"
      />
      <FormMessage
        v-if="error"
        :text="error"
        tone="error"
      />
      <template v-if="isRoot && state.reply_count">
        <button
          v-if="!repliesOpen"
          type="button"
          class="text-button text-button-inline comment-replies-toggle"
          :aria-expanded="false"
          @click="openReplies"
        >
          {{ $t('comments.show_replies', { count: state.reply_count }) }}
        </button>
        <button
          v-else
          type="button"
          class="text-button text-button-inline comment-replies-toggle"
          :aria-expanded="true"
          @click="repliesOpen = false"
        >
          {{ $t('comments.hide_replies') }}
        </button>
      </template>
      <FormMessage
        v-if="isRoot && !repliesOpen && repliesError"
        :text="repliesError"
        tone="error"
      />
      <div
        v-if="isRoot && repliesOpen"
        class="comment-replies"
      >
        <CommentItem
          v-for="reply in replies"
          :key="reply.id"
          :comment="reply"
          :article-id="articleId"
          :root-id="state.id"
          @replied="addReply"
        />
        <LoadMore
          v-if="repliesMore"
          :busy="repliesBusy"
          :error="repliesError"
          @more="moreReplies"
        />
      </div>
    </div>
  </article>
</template>
