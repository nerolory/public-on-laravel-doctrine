/** Значение-объект без массивов: проверка ответа сервера перед использованием в коде. */
export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** Значение-список любых элементов. */
export function isList(value: unknown): value is unknown[] {
  return Array.isArray(value)
}

/** Список строк. */
export function isStringList(value: unknown): value is string[] {
  return isList(value) && value.every(item => typeof item === 'string')
}
