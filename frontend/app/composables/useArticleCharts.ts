import { drawChart } from '~~/editor/rich-editor/chart-view'
import type { ChartView } from '~~/editor/rich-editor/chart-view'
import type { ChartResult } from '~~/editor/rich-editor'

/**
 * Рисует графики в тексте статьи: в каждый блок figure[data-chart] перед таблицей данных вставляется график.
 * Таблица остаётся для экранных дикторов и скрывается визуально, график скрыт от них.
 * Если график не нарисовался, таблица остаётся видимой: данные графика читаются из неё, на месте графика
 * выводится сообщение об ошибке.
 */
export function useArticleCharts(
  container: Ref<HTMLElement | null>,
  charts: () => Record<string, ChartResult> | undefined,
) {
  const { t } = useI18n()
  let views: ChartView[] = []

  const clear = () => {
    views.forEach(view => view.destroy())
    views = []
  }

  /** График в одном блоке; при ошибке отрисовки область графика заменяется видимым сообщением. */
  async function draw(chart: ChartResult, table: HTMLTableElement): Promise<void> {
    const area = document.createElement('div')

    area.setAttribute('aria-hidden', 'true')
    table.before(area)

    try {
      views.push(await drawChart(area, chart))
      table.classList.add('visually-hidden')
    } catch {
      const notice = document.createElement('p')

      notice.className = 'error'
      notice.setAttribute('role', 'alert')
      notice.textContent = t('editor.chart_unavailable')
      area.replaceWith(notice)
    }
  }

  async function mount(): Promise<void> {
    clear()

    const data = charts()

    if (!data) {
      return
    }

    const root = container.value

    if (!root) {
      return
    }

    for (const figure of root.querySelectorAll<HTMLElement>('figure[data-chart]')) {
      const chart = figure.dataset.chart ? data[figure.dataset.chart] : undefined
      const table = figure.querySelector('table')

      if (chart && table) {
        await draw(chart, table)
      }
    }
  }

  onMounted(mount)
  watch(charts, () => nextTick(mount))
  onBeforeUnmount(clear)
}
