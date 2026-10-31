import { ApiRequestError, isApiErrorData } from '../../shared/utils/api-errors'

/** CSRF-токен страницы MoonShine из meta-тега. */
function csrf(): string {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content

  if (!token) {
    throw new Error('На странице нет мета-тега csrf-token')
  }

  return token
}

/** Тело ответа: JSON разбирается, ответ без JSON не имеет тела. */
async function payload(response: Response): Promise<unknown> {
  return response.headers.get('content-type')?.includes('application/json') ? response.json() : undefined
}

/**
 * Запрос к API от имени администратора: сессия, CSRF и JSON. Ответ возвращается без приведения типа,
 * его проверяет вызывающий; при ошибке бросает ApiRequestError.
 */
export async function request(
  url: string,
  method: 'GET' | 'POST',
  body?: Record<string, unknown> | FormData,
): Promise<unknown> {
  const json = body !== undefined && !(body instanceof FormData)
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrf(),
      ...(json ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body instanceof FormData ? body : JSON.stringify(body),
  })
  const data = await payload(response)

  if (!response.ok) {
    throw new ApiRequestError(response.status, isApiErrorData(data) ? data : undefined)
  }

  return data
}
