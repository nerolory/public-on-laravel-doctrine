/** Ответ бэкенда «сначала примите новые правила»: в нём документы, которые нужно принять. */
type ConsentRequiredError = ApiError & { data: { documents: PendingDocument[] } }

/** Код ответа 409, когда обязательные документы обновились. */
const CONSENTS_REQUIRED = 'consents_required'

/**
 * Окно принятия новых правил. Бэкенд отвечает 409 с кодом consents_required, если обязательные документы обновились;
 * после принятия запрос повторяется автоматически.
 */
export function useConsentGate() {
  const open = useState('consent-gate-open', () => false)
  const documents = useState<PendingDocument[]>('consent-gate-documents', () => [])
  /** Ожидающий ответ окна: заполняется только в браузере, пока окно открыто. */
  const resolver = useState<((accepted: boolean) => void) | null>('consent-gate-resolver', () => null)

  function ask(pending: PendingDocument[]): Promise<boolean> {
    documents.value = pending
    open.value = true

    return new Promise((resolve) => {
      resolver.value = resolve
    })
  }

  function close(accepted: boolean) {
    open.value = false
    resolver.value?.(accepted)
    resolver.value = null
  }

  return { open, documents, ask, close }
}

/** Бэкенд требует принять обновлённые правила перед этим запросом. */
export function isConsentRequired(error: unknown): error is ConsentRequiredError {
  return isApiError(error)
    && error.status === 409
    && error.data?.code === CONSENTS_REQUIRED
    && isList(error.data.documents)
}
