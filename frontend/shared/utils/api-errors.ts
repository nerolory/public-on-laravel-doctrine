import { isList, isRecord, isStringList } from './guards'

/** Документ, который пользователь должен принять, прежде чем бэкенд выполнит запрос. */
export type PendingDocument = { type: string, title: string, text: string }

/** Тело ответа бэкенда с ошибкой. */
type ApiErrorData = {
  message?: string
  code?: string
  errors?: Record<string, string[]>
  documents?: PendingDocument[]
}

/** Ошибка запроса к API: статус и тело ответа сервера. */
export type ApiError = { status?: number, data?: ApiErrorData }

/** Ошибка ответа бэкенда для запросов без $fetch: статус и тело, если сервер вернул его в виде ошибки API. */
export class ApiRequestError extends Error implements ApiError {
  constructor(
    readonly status: number,
    readonly data?: ApiErrorData,
  ) {
    super(data?.message ?? `HTTP ${status}`)
  }
}

/** Ошибка пришла от API: у неё есть статус ответа. */
export function isApiError(error: unknown): error is ApiError {
  return typeof error === 'object' && error !== null && 'status' in error
}

/** Тело ошибки API: все поля необязательны, но присутствующие поля должны иметь свой тип. */
export function isApiErrorData(value: unknown): value is ApiErrorData {
  if (!isRecord(value)) {
    return false
  }

  return (value.message === undefined || typeof value.message === 'string')
    && (value.code === undefined || typeof value.code === 'string')
    && (value.errors === undefined || isStringListRecord(value.errors))
    && (value.documents === undefined || (isList(value.documents) && value.documents.every(isRecord)))
}

/** Код ошибки из ответа API: по нему отличают ожидаемые отказы (конфликт версий, новые правила). */
export function apiErrorCode(error: unknown): string | undefined {
  return isApiError(error) ? error.data?.code : undefined
}

/** Сообщения об ошибках из ответа API одной строкой; без ответа сервера — переданный текст. */
export function apiErrorText(error: unknown, fallback: string): string {
  if (!isApiError(error)) {
    return fallback
  }

  return Object.values(error.data?.errors ?? {}).flat().join(' ') || error.data?.message || fallback
}

/** Поля ошибок: имя поля → список сообщений. */
function isStringListRecord(value: unknown): value is Record<string, string[]> {
  return isRecord(value) && Object.values(value).every(isStringList)
}
