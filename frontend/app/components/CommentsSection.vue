<script setup lang="ts">
import type { CommentPage, CommentView } from '~/composables/useComments'

/** Комментарии под статьёй: сортировка «популярные» или «новые», ответы раскрываются по ветке. */
const props = defineProps<{ articleId: number }>()
const { user, submit } = useAccount()
const { get } = useApi()
const { localePath } = useLocalization()
const { t } = useI18n()
const { busy: posting, error: postError, run: runPost } = useFormAction()
const uid = useId()

/** Порядок веток: популярные или новые. */
const SORT_OPTIONS = ['top', 'new'] as const

const sort = ref<typeof SORT_OPTIONS[number]>('top')
const path = computed(() => apiPaths.articleComments(props.articleId))
const { data, error: firstPageError } = await useApiFetch<CommentPage>(() => path.value, {
  query: computed(() => ({ sort: sort.value, page: 1 })),
})
const firstPageText = computed(() => apiErrorText(firstPageError.value, t('comments.load_failed')))

/** Общее число комментариев: растёт при добавлении нового. */
const total = ref(0)
watch(data, (value) => {
  if (value) {
    total.value = value.total
  }
}, { immediate: true })

const { items: threads, hasMore, busy: loadingMore, error: loadError, more, prepend } = usePagedList<CommentView>(
  () => data.value,
  page => get<CommentPage>(path.value, { sort: sort.value, page }),
  'comments.load_failed',
)

const body = ref('')
async function post() {
  await runPost(async () => {
    const comment = await submit<CommentView>(path.value, 'POST', { body: body.value })

    prepend(comment)
    total.value++
    body.value = ''
  }, { failure: 'comments.failed' })
}
</script>

<template>
  <section
    class="comments"
    :aria-labelledby="`${uid}-title`"
  >
    <div class="comments-head">
      <h2 :id="`${uid}-title`">
        {{ $t('comments.title', { count: total }) }}
      </h2>
      <div
        class="comments-sort"
        role="group"
        :aria-label="$t('comments.sort')"
      >
        <button
          v-for="option in SORT_OPTIONS"
          :key="option"
          type="button"
          class="filter-chip"
          :class="{ active: sort === option }"
          :aria-pressed="sort === option"
          @click="sort = option"
        >
          {{ $t(`comments.sort_${option}`) }}
        </button>
      </div>
    </div>
    <CommentForm
      v-if="user"
      :id="`${uid}-body`"
      v-model="body"
      :label="$t('comments.new')"
      :placeholder="$t('comments.new')"
      :submit-text="$t('comments.send')"
      :busy="posting"
      :error="postError"
      :rows="3"
      @submit="post"
    />
    <p
      v-else
      class="field-hint"
    >
      <NuxtLink
        :to="localePath('/login')"
        class="text-link"
      >{{ $t('nav.login') }}</NuxtLink>, {{ $t('comments.login_to_comment') }}
    </p>
    <FormMessage
      v-if="firstPageError"
      :text="firstPageText"
      tone="error"
    />
    <p
      v-else-if="!threads.length"
      class="state"
    >
      {{ $t('comments.empty') }}
    </p>
    <CommentItem
      v-for="comment in threads"
      :key="comment.id"
      :comment="comment"
      :article-id="articleId"
    />
    <LoadMore
      v-if="hasMore"
      :busy="loadingMore"
      :error="loadError"
      @more="more"
    />
  </section>
</template>
