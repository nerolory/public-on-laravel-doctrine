<script setup lang="ts">
import { POLL_MIN_OPTIONS, emptyPoll } from '~/composables/useArticles'
import type { PollForm } from '~/composables/useArticles'

/** Опрос статьи: один на статью. Варианты, за которые уже голосовали, сервер менять не даст — поля блокируются. */
const poll = defineModel<PollForm | null>({ required: true })
const limits = useFormLimits()
const uid = useId()
</script>

<template>
  <fieldset class="poll-editor">
    <legend>{{ $t('poll.editor_title') }}</legend>
    <button
      v-if="!poll"
      type="button"
      class="text-button text-button-inline"
      @click="poll = emptyPoll()"
    >
      {{ $t('poll.add') }}
    </button>
    <template v-else>
      <label>{{ $t('poll.question') }}<input
        v-model="poll.question"
        required
        :maxlength="limits.poll_question"
      ></label>
      <p
        v-if="poll.locked"
        class="field-hint"
      >
        {{ $t('poll.locked') }}
      </p>
      <ol class="poll-editor-options">
        <li
          v-for="(_, index) in poll.options"
          :key="index"
        >
          <input
            v-model="poll.options[index]"
            required
            :maxlength="limits.poll_option_length"
            :disabled="poll.locked"
            :aria-label="$t('poll.option', { number: index + 1 })"
          >
          <button
            v-if="poll.options.length > POLL_MIN_OPTIONS && !poll.locked"
            type="button"
            class="poll-editor-remove"
            :aria-label="$t('poll.remove_option', { number: index + 1 })"
            @click="poll.options.splice(index, 1)"
          >
            <span aria-hidden="true">×</span>
          </button>
        </li>
      </ol>
      <button
        v-if="!poll.locked && poll.options.length < limits.poll_options"
        type="button"
        class="text-button text-button-inline"
        @click="poll.options.push('')"
      >
        {{ $t('poll.add_option') }}
      </button>
      <label class="consent-check"><input
        v-model="poll.multiple"
        type="checkbox"
      > {{ $t('poll.multiple') }}</label>
      <label v-if="poll.multiple">{{ $t('poll.max_choices') }}<input
        v-model.number="poll.max_choices"
        type="number"
        min="1"
        :max="poll.options.length"
        :placeholder="$t('poll.max_choices_any')"
      ></label>
      <label :for="`${uid}-ends`">{{ $t('poll.ends_at') }}</label>
      <input
        :id="`${uid}-ends`"
        v-model="poll.ends_at"
        type="datetime-local"
        :aria-describedby="`${uid}-ends-hint`"
      >
      <small
        :id="`${uid}-ends-hint`"
        class="field-hint"
      >{{ $t('poll.ends_at_hint') }}</small>
      <label class="consent-check"><input
        v-model="poll.allow_revote"
        type="checkbox"
      > {{ $t('poll.allow_revote') }}</label>
      <label class="consent-check"><input
        v-model="poll.anonymous"
        type="checkbox"
      > {{ $t('poll.anonymous') }}</label>
      <button
        type="button"
        class="text-button text-button-inline danger-text"
        @click="poll = null"
      >
        {{ $t('poll.remove') }}
      </button>
    </template>
  </fieldset>
</template>
