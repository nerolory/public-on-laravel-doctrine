import type { ChartConfiguration } from 'chart.js'
import type { ChartPayload } from './types'

/** Нарисованный график; destroy убирает его со страницы. */
export type ChartView = { destroy: () => void }

/** Префикс CSS-переменных палитры графиков (--chart-color-1, --chart-color-2…), их задают стили. */
const COLOR_PREFIX = '--chart-color-'

/** Цвета рядов из CSS-переменных палитры по порядку номеров. */
function palette(): string[] {
  const style = getComputedStyle(document.documentElement)
  const read = (index: number) => style.getPropertyValue(`${COLOR_PREFIX}${index}`).trim()
  const colors: string[] = []
  let color = read(1)

  for (let index = 2; color !== ''; index++) {
    colors.push(color)
    color = read(index)
  }

  return colors
}

/** Настройки Chart.js: у круговой диаграммы один ряд с цветом на долю, у остальных — цвет на ряд. */
function configuration(chart: ChartPayload, colors: string[]): ChartConfiguration {
  const options = { responsive: true, maintainAspectRatio: false, animation: false as const }

  if (chart.type === 'pie') {
    return {
      type: 'pie',
      data: {
        labels: chart.labels,
        datasets: chart.series.slice(0, 1).map(series => ({ data: series.values, backgroundColor: colors })),
      },
      options: { ...options, plugins: { legend: { position: 'bottom' } } },
    }
  }

  return {
    type: chart.type,
    data: {
      labels: chart.labels,
      datasets: chart.series.map((series, index) => {
        const color = colors[index % colors.length]

        return { label: series.name, data: series.values, backgroundColor: color, borderColor: color }
      }),
    },
    options: { ...options, plugins: { legend: { display: chart.series.length > 1, position: 'bottom' } } },
  }
}

/** Рисует график в элементе; библиотека графиков загружается при первом графике на странице. */
export async function drawChart(element: HTMLElement, chart: ChartPayload): Promise<ChartView> {
  const { Chart } = await import('./chart-library')
  const canvas = document.createElement('canvas')

  element.classList.add('chart-view')
  element.append(canvas)

  const instance = new Chart(canvas, configuration(chart, palette()))

  return {
    destroy: () => {
      instance.destroy()
      canvas.remove()
    },
  }
}
