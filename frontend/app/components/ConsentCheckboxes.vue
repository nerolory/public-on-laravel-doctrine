<script setup lang="ts">
import { isCookieConsent } from '~/composables/useLegal'
import type { LegalDocument } from '~/composables/useLegal'

/** Согласия формы регистрации: обязательные и добровольные документы; согласия на cookies даёт баннер. */

const props = defineProps<{ documents: LegalDocument[] }>()
const model = defineModel<Record<string, boolean>>({ required: true })
// Локальная копия обновляется сразу: несколько отметок подряд не теряются, пока родитель получает новое значение.
const checked = ref<Record<string, boolean>>({ ...model.value })
watch(model, (value) => {
  checked.value = { ...value }
})
const visible = computed(() => props.documents.filter(item => !isCookieConsent(item.type)))

function toggle(type: string, event: Event) {
  const value = event.target instanceof HTMLInputElement && event.target.checked

  checked.value = { ...checked.value, [type]: value }
  model.value = { ...checked.value }
}
</script>

<template>
  <fieldset
    v-if="visible.length"
    class="consents"
  >
    <legend>{{ $t('legal.consents_title') }}</legend>
    <div
      v-for="item in visible"
      :key="item.type"
      class="consent"
    >
      <label class="consent-check">
        <input
          type="checkbox"
          :checked="!!checked[item.type]"
          :required="item.required"
          @change="toggle(item.type, $event)"
        >
        <span>{{ item.title }} <small>({{ item.required ? $t('legal.required') : $t('legal.optional') }})</small></span>
      </label>
      <ConsentDocument
        :summary="$t('legal.read')"
        :text="item.text"
      />
    </div>
  </fieldset>
</template>
