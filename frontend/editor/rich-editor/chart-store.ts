import type { ChartPayload, ChartResult, RichEditorApi } from './types'

/** Данные графиков редактора: загружаются с сервера один раз, только что созданные кладутся сразу. */
export type ChartStore = {
  get: (id: string) => Promise<ChartPayload>
  put: (chart: ChartResult) => void
}

/** Хранилище данных графиков одного редактора. */
export function createChartStore(api: RichEditorApi): ChartStore {
  const charts = new Map<string, Promise<ChartPayload>>()

  return {
    get(id) {
      const cached = charts.get(id)

      if (cached) {
        return cached
      }

      // Неудачная загрузка убирается из кэша, чтобы следующий запрос повторил её; ошибка уходит вызывающему.
      const loading = api.loadChart(id).catch((error: unknown) => {
        charts.delete(id)

        throw error
      })

      charts.set(id, loading)

      return loading
    },
    put(chart) {
      charts.set(chart.id, Promise.resolve(chart))
    },
  }
}
