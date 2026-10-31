import type { ArticleFormData } from '~/composables/useArticles'
import type { FormMessages } from '~/composables/useFormAction'
import type { MutationMethod } from '~/composables/useApi'

/** Адрес статьи и метод отправки; для правки передаётся версия, с которой начата правка. */
type ArticleTarget = { path: string, method: MutationMethod, version?: number }

/** Код ответа, когда статью изменили после открытия формы: форма правки должна загрузить актуальную версию. */
const VERSION_CONFLICT = 'version_conflict'

/** Сохранение статьи для новой статьи и правки: отправка, сообщение о результате и признак конфликта версий. */
export function useArticleSave() {
  const { submit } = useAccount()
  const { busy, notice, error, run, reset: resetNotice } = useFormAction()
  const conflict = ref(false)

  /** Отправляет статью; возвращает true, если сервер её принял. */
  function save(form: ArticleFormData, target: ArticleTarget, messages: FormMessages): Promise<boolean> {
    conflict.value = false

    const body = { ...articlePayload(form), ...(target.version === undefined ? {} : { version: target.version }) }

    return run(async () => {
      try {
        await submit(target.path, target.method, body)
      } catch (failure) {
        conflict.value = apiErrorCode(failure) === VERSION_CONFLICT

        throw failure
      }
    }, messages)
  }

  /** Убирает сообщение и признак конфликта перед перезагрузкой статьи. */
  function reset() {
    conflict.value = false
    resetNotice()
  }

  return { busy, notice, error, conflict, save, reset }
}
