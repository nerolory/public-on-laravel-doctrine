/** Сообщение под формой: успех или ошибка. */
type FormNotice = { text: string, error: boolean }

/** Ключи переводов для сообщений о результате; без success успешное действие сообщения не показывает. */
export type FormMessages = { success?: string, failure: string }

/** Отправка формы: флаг занятости и сообщение с результатом. */
export function useFormAction() {
  const { t } = useI18n()
  const busy = ref(false)
  const notice = ref<FormNotice | null>(null)
  /** Текст ошибки последнего действия. */
  const error = computed(() => notice.value?.error ? notice.value.text : '')

  /** Выполняет действие и запоминает сообщение; возвращает, удалось ли действие. */
  async function run(action: () => Promise<unknown>, messages: FormMessages): Promise<boolean> {
    busy.value = true
    notice.value = null

    try {
      await action()
      notice.value = messages.success ? { text: t(messages.success), error: false } : null

      return true
    } catch (failure) {
      notice.value = { text: apiErrorText(failure, t(messages.failure)), error: true }

      return false
    } finally {
      busy.value = false
    }
  }

  /** Убирает сообщение: при открытии формы заново. */
  function reset() {
    notice.value = null
  }

  return { busy, notice, error, run, reset }
}
