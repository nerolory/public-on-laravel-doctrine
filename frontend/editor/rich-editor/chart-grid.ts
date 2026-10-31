import { el, field, input, removeButton, select, textButton } from './dom'
import type { ChartPayload, ChartType, Label, RichEditorLimits } from './types'

/** Поля окна графика и чтение введённой таблицы. */
type ChartGrid = { fields: HTMLElement[], value: () => ChartPayload }

/** Виды графиков в порядке показа в списке. */
const CHART_TYPES: ChartType[] = ['bar', 'line', 'pie']

/** Проверка значения списка видов графика по CHART_TYPES. */
function isChartType(value: string): value is ChartType {
  return CHART_TYPES.some(item => item === value)
}

/** Таблица данных для графика: строки — подписи, столбцы — ряды. Сервер хранит таблицу, график рисует браузер. */
export function chartGrid(initial: ChartPayload, label: Label, limits: RichEditorLimits): ChartGrid {
  const state: ChartPayload = structuredClone(initial)
  const type = select(CHART_TYPES.map(value => ({ value, text: label(`chart_${value}`) })), state.type)
  const title = input({ type: 'text', value: state.title, required: true, maxlength: limits.chart_title })
  const table = el('table', { class: 'rich-editor-grid' })
  const addRow = textButton(label('chart_add_row'), () => {
    state.labels.push('')
    state.series.forEach(series => series.values.push(0))
    draw()
  })
  const addSeries = textButton(label('chart_add_series'), () => {
    state.series.push({ name: '', values: state.labels.map(() => 0) })
    draw()
  })

  /** Тип графика из списка; у круговой диаграммы остаётся один ряд. */
  const sync = () => {
    if (!isChartType(type.value)) {
      throw new Error(`Неизвестный вид графика: ${type.value}`)
    }

    state.type = type.value

    if (state.type === 'pie') {
      state.series = state.series.slice(0, 1)
    }

    addSeries.hidden = state.type === 'pie' || state.series.length >= limits.chart_series
    addRow.hidden = state.labels.length >= limits.chart_labels
  }

  const draw = () => {
    sync()
    table.replaceChildren(
      headRow(state, label, limits, draw),
      ...state.labels.map((_, row) => dataRow(state, row, label, limits, draw)),
    )
  }

  type.addEventListener('change', draw)
  title.addEventListener('input', () => {
    state.title = title.value
  })
  draw()

  return {
    fields: [
      field(label('chart_name'), title),
      field(label('chart_type'), type),
      el('p', { class: 'rich-editor-hint' }, [label('chart_hint')]),
      el('div', { class: 'rich-editor-grid-wrap' }, [table]),
      el('div', { class: 'rich-editor-grid-controls' }, [addRow, addSeries]),
    ],
    value: () => ({
      ...state,
      title: state.title.trim(),
      labels: state.labels.map(text => text.trim()),
      series: state.series.map(series => ({ name: series.name.trim(), values: [...series.values] })),
    }),
  }
}

/** Заголовок таблицы: названия рядов с кнопками удаления. */
function headRow(state: ChartPayload, label: Label, limits: RichEditorLimits, draw: () => void): HTMLElement {
  const head = el('tr', {}, [el('th', { scope: 'col' }, [label('chart_label')])])

  state.series.forEach((series, index) => {
    const number = index + 1
    const name = input({
      'type': 'text',
      'value': series.name,
      'required': true,
      'maxlength': limits.chart_label,
      'aria-label': label('chart_series', { number }),
    })
    const cell = el('th', { scope: 'col' }, [name])

    name.addEventListener('input', () => {
      series.name = name.value
    })

    if (state.series.length > 1) {
      cell.append(removeButton(label('chart_remove_series', { number }), () => {
        state.series.splice(index, 1)
        draw()
      }))
    }

    head.append(cell)
  })

  return head
}

/** Строка таблицы: подпись и значения каждого ряда. */
function dataRow(
  state: ChartPayload,
  row: number,
  label: Label,
  limits: RichEditorLimits,
  draw: () => void,
): HTMLElement {
  const number = row + 1
  const text = state.labels[row]

  // Строка таблицы строится по подписям, поэтому подпись на её индексе есть всегда.
  if (text === undefined) {
    throw new Error(`В таблице графика нет подписи строки ${number}`)
  }

  const name = input({
    'type': 'text',
    'value': text,
    'required': true,
    'maxlength': limits.chart_label,
    'aria-label': `${label('chart_label')} ${number}`,
  })
  const tr = el('tr', {}, [el('td', {}, [name])])

  name.addEventListener('input', () => {
    state.labels[row] = name.value
  })

  state.series.forEach((series, index) => {
    const amount = series.values[row]

    // Значения каждого ряда имеют ту же длину, что и подписи строк.
    if (amount === undefined) {
      throw new Error(`В ряду ${index + 1} нет значения строки ${number}`)
    }

    const value = input({
      'type': 'number',
      'step': 'any',
      'value': String(amount),
      'required': true,
      'aria-label': `${label('chart_series', { number: index + 1 })}, ${number}`,
    })

    value.addEventListener('input', () => {
      series.values[row] = Number(value.value)
    })
    tr.append(el('td', {}, [value]))
  })

  if (state.labels.length > 1) {
    tr.append(el('td', {}, [removeButton(label('chart_remove_row', { number }), () => {
      state.labels.splice(row, 1)
      state.series.forEach(series => series.values.splice(row, 1))
      draw()
    })]))
  }

  return tr
}
