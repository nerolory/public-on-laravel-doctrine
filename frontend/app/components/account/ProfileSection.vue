<script setup lang="ts">
import type { Account } from '~/composables/useAccount'

/** Анкета профиля и видимость её полей; правится копия, сохраняется одной кнопкой. */
const props = defineProps<{ user: Account }>()

const { submit, refresh } = useAccount()
const { busy, notice, run } = useFormAction()
const limits = useFormLimits()
/** Черновик анкеты; ссылки на соцсети ключуются кодом сети из списка networks пользователя. */
type ProfileForm = {
  last_name: string
  first_name: string
  middle_name: string
  birth_date: string
  gender: string
  about: string
  contacts: { phone: string, email: string }
  socials: Record<string, string>
}

const profile = reactive<ProfileForm>({
  last_name: '',
  first_name: '',
  middle_name: '',
  birth_date: '',
  gender: '',
  about: '',
  contacts: { phone: '', email: '' },
  socials: {},
})
const visibility = reactive({
  name: false,
  birth_date: false,
  gender: false,
  about: false,
  contacts: false,
  socials: false,
})

watch(() => props.user, (user) => {
  const { profile: saved } = user

  Object.assign(profile, {
    last_name: saved.last_name ?? '',
    first_name: saved.first_name ?? '',
    middle_name: saved.middle_name ?? '',
    birth_date: saved.birth_date ?? '',
    gender: saved.gender ?? '',
    about: saved.about ?? '',
    contacts: { phone: saved.contacts.phone ?? '', email: saved.contacts.email ?? '' },
    socials: Object.fromEntries(user.networks.map(network => [network, saved.socials[network] ?? ''])),
  })
  Object.assign(visibility, user.visibility)
}, { immediate: true })

const save = () => run(async () => {
  await submit(apiPaths.userProfile, 'PUT', {
    ...profile,
    gender: profile.gender || null,
    birth_date: profile.birth_date || null,
    visibility: { ...visibility },
  })
  await refresh()
}, { success: 'profile.saved', failure: 'profile.failed' })
</script>

<template>
  <AccountSection :title="$t('profile.section')">
    <p class="form-intro">
      {{ $t('profile.intro') }}
    </p>
    <form
      class="profile-form"
      @submit.prevent="save"
    >
      <AccountProfileRow
        v-model="visibility.name"
        :label="$t('profile.first_name')"
      >
        <div class="profile-fields">
          <label>{{ $t('profile.last_name') }}<input
            v-model="profile.last_name"
            autocomplete="family-name"
            :maxlength="limits.person_name"
          ></label>
          <label>{{ $t('profile.first_name') }}<input
            v-model="profile.first_name"
            autocomplete="given-name"
            :maxlength="limits.person_name"
          ></label>
          <label>{{ $t('profile.middle_name') }}<input
            v-model="profile.middle_name"
            autocomplete="additional-name"
            :maxlength="limits.person_name"
          ></label>
        </div>
      </AccountProfileRow>
      <AccountProfileRow
        v-model="visibility.birth_date"
        :label="$t('profile.birth_date')"
      >
        <label>{{ $t('profile.birth_date') }}<input
          v-model="profile.birth_date"
          type="date"
          autocomplete="bday"
        ></label>
      </AccountProfileRow>
      <AccountProfileRow
        v-model="visibility.gender"
        :label="$t('profile.gender')"
      >
        <label>{{ $t('profile.gender') }}<select
          v-model="profile.gender"
          class="article-input"
        >
          <option value="">{{ $t('profile.gender_none') }}</option>
          <option value="male">{{ $t('profile.gender_male') }}</option>
          <option value="female">{{ $t('profile.gender_female') }}</option>
        </select></label>
      </AccountProfileRow>
      <AccountProfileRow
        v-model="visibility.about"
        :label="$t('profile.about')"
      >
        <label>{{ $t('profile.about') }}<textarea
          v-model="profile.about"
          class="article-input"
          rows="4"
          :maxlength="limits.about"
        /></label>
      </AccountProfileRow>
      <AccountProfileRow
        v-model="visibility.contacts"
        :label="$t('profile.contacts')"
      >
        <div class="profile-fields">
          <label>{{ $t('profile.phone') }}<input
            v-model="profile.contacts.phone"
            type="tel"
            autocomplete="tel"
            :maxlength="limits.phone"
          ></label>
          <label>{{ $t('profile.contact_email') }}<input
            v-model="profile.contacts.email"
            type="email"
            :maxlength="limits.email"
          ></label>
        </div>
      </AccountProfileRow>
      <AccountProfileRow
        v-model="visibility.socials"
        :label="$t('profile.socials')"
      >
        <div class="profile-fields">
          <label
            v-for="network in user.networks"
            :key="network"
          >{{ $t(`profile.networks.${network}`) }}<input
            v-model="profile.socials[network]"
            type="url"
            inputmode="url"
            placeholder="https://"
            :maxlength="limits.social_url"
          ></label>
        </div>
      </AccountProfileRow>
      <FormMessage
        v-if="notice"
        :text="notice.text"
        :tone="notice.error ? 'error' : 'success'"
      />
      <button
        type="submit"
        class="primary-button"
        :disabled="busy"
      >
        {{ $t('profile.save') }} <span aria-hidden="true">↗</span>
      </button>
    </form>
  </AccountSection>
</template>
