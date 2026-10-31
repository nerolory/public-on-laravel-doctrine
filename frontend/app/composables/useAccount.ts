import type { MutationMethod, RequestBody } from '~/composables/useApi'
import type { ImageView } from '#shared/types/content'

/** Учётная запись текущего пользователя — как её отдаёт /api/session. */
export type Account = {
  id: number
  email: string
  email_verified: boolean
  name: string
  public_name: string
  avatar: ImageView | null
  profile: {
    last_name: string | null
    first_name: string | null
    middle_name: string | null
    birth_date: string | null
    gender: string | null
    about: string | null
    contacts: Record<string, string>
    socials: Record<string, string>
  }
  visibility: Record<string, boolean>
  networks: string[]
  capabilities: { delete_account: boolean, admin: boolean, export_data: boolean }
}

/** Сессия посетителя и отправка форм от его имени. */
export function useAccount() {
  const user = useState<Account | null>('account', () => null)
  const loaded = useState('account-loaded', () => false)
  const headers = useApiHeaders()
  const { send } = useApi()
  const gate = useConsentGate()
  /** Состояние выхода: занятость и ошибка для кнопки выхода в меню и в кабинете. */
  const leaving = useFormAction()

  /** Состояние сессии: гость получает user = null. Ошибка запроса передаётся вызывающему. */
  async function refresh() {
    user.value = (await $fetch<{ user: Account | null }>(apiPaths.session, { headers })).user
    loaded.value = true
  }

  /** Отправка формы; если правила сайта обновились, сначала предлагается принять их, затем запрос повторяется. */
  async function submit<T = unknown>(path: string, method: MutationMethod, body?: RequestBody): Promise<T> {
    try {
      return await send<T>(path, method, body)
    } catch (error) {
      if (isConsentRequired(error) && await gate.ask(error.data.documents)) {
        return await send<T>(path, method, body)
      }

      throw error
    }
  }

  /** Выход: сессия завершается на сервере и перечитывается. */
  async function logout() {
    await submit(apiPaths.logout, 'POST')
    await refresh()
  }

  /** Выход и переход на страницу path; при ошибке остаётся на месте и показывает её в leaving.error. */
  async function leave(path: string): Promise<boolean> {
    const left = await leaving.run(logout, { failure: 'menu.logout_failed' })

    if (left) {
      await navigateTo(path)
    }

    return left
  }

  return { user, loaded, refresh, submit, leave, leaving }
}
