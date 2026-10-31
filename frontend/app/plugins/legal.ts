import type { LegalTerms } from '~/composables/useLegal'

/** Условия правового региона загружаются до отрисовки страниц и доступны как $legalTerms. */
export default defineNuxtPlugin({
  name: 'legal',
  dependsOn: ['i18n'],
  async setup() {
    const legalTerms = await useStoredFetch<LegalTerms>(
      'legal-terms',
      () => $fetch<LegalTerms>(apiPaths.legal, { headers: useApiHeaders() }),
    )

    return { provide: { legalTerms } }
  },
})
