<script setup lang="ts">
/** Редактор статьи на сайте: тонкая обёртка над общим модулем editor/rich-editor (тот же код работает в админке). */
import { createRichEditor } from '~~/editor/rich-editor'
import type { RichEditorLimits } from '~~/editor/rich-editor'
import { createEditorApi } from '~~/editor/rich-editor/api'
import '~~/editor/rich-editor/rich-editor.scss'

const html = defineModel<string>({ required: true })
const props = defineProps<{ limits: RichEditorLimits, labelledBy: string }>()
const { t, tm } = useI18n()
const { submit } = useAccount()
const { get } = useApi()
const host = ref<HTMLElement | null>(null)
let instance: ReturnType<typeof createRichEditor> | null = null

/** Подстановки {max} и {number} оставляются как есть: их заполняет сам редактор. */
function labels(): Record<string, string> {
  return Object.fromEntries(
    Object.keys(tm('editor')).map(key => [key, t(`editor.${key}`, { max: '{max}', number: '{number}' })]),
  )
}

/**
 * Редактор создаётся, когда появился контейнер. onMounted не подходит: внутри вложенных async-компонентов
 * при гидратации ref может быть ещё пустым в момент монтирования.
 */
watch(host, (element) => {
  if (!element || instance) {
    return
  }

  instance = createRichEditor({
    element,
    content: html.value,
    labels: labels(),
    limits: props.limits,
    labelledBy: props.labelledBy,
    errorText: apiErrorText,
    onChange: (value) => {
      html.value = value
    },
    api: createEditorApi({ get, post: (path, body) => submit(path, 'POST', body) }),
  })
}, { flush: 'post', immediate: true })
watch(html, value => instance?.setContent(value))
onBeforeUnmount(() => instance?.destroy())
</script>

<template>
  <div
    ref="host"
    class="rich-editor-host"
  />
</template>
