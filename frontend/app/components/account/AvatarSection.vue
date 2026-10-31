<script setup lang="ts">
import type { Account } from '~/composables/useAccount'

/** Аватар: загрузка, замена и удаление; результат виден по самой картинке, сообщение — только об ошибке. */
defineProps<{ user: Account }>()

const { send } = useApi()
const { refresh } = useAccount()
const { busy, error, run } = useFormAction()

async function upload(event: Event) {
  if (!(event.target instanceof HTMLInputElement)) {
    return
  }

  const input = event.target
  const file = selectedFiles(input)[0]

  if (file) {
    await run(async () => {
      await send(apiPaths.userAvatar, 'POST', imageForm(file))
      await refresh()
    }, { failure: 'avatar.failed' })
  }

  input.value = ''
}

const remove = () => run(async () => {
  await send(apiPaths.userAvatar, 'DELETE')
  await refresh()
}, { failure: 'avatar.failed' })
</script>

<template>
  <AccountSection :title="$t('avatar.section')">
    <div class="avatar-row">
      <img
        v-if="user.avatar"
        class="avatar-large"
        :src="user.avatar.url"
        :srcset="user.avatar.srcset"
        :sizes="IMAGE_SIZES.avatarLarge"
        width="96"
        height="96"
        :alt="$t('avatar.alt')"
      >
      <span
        v-else
        class="avatar-large avatar-empty"
        aria-hidden="true"
      >{{ initialOf(user.public_name) }}</span>
      <div>
        <label class="file-button">{{ user.avatar ? $t('avatar.change') : $t('avatar.upload') }}<input
          type="file"
          :accept="AVATAR_IMAGE_TYPES"
          class="visually-hidden"
          :disabled="busy"
          @change="upload"
        ></label>
        <button
          v-if="user.avatar"
          type="button"
          class="text-button"
          :disabled="busy"
          @click="remove"
        >
          {{ $t('avatar.remove') }}
        </button>
        <p class="form-intro">
          {{ $t('avatar.hint') }}
        </p>
      </div>
    </div>
    <FormMessage
      v-if="error"
      :text="error"
      tone="error"
    />
  </AccountSection>
</template>
