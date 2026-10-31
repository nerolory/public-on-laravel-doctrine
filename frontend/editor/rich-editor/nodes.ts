import { Node as TiptapNode, mergeAttributes } from '@tiptap/core'
import Image from '@tiptap/extension-image'
import type { AlbumLayout } from '../../shared/types/content'
import type { ChartStore } from './chart-store'
import { drawChart } from './chart-view'
import type { ChartView } from './chart-view'
import { el } from './dom'
import type { Label } from './types'

/** Виды показа альбома: слайдер или коллаж. */
export const ALBUM_LAYOUTS: AlbumLayout[] = ['slider', 'collage']

/** Вид альбома, если в разметке он не указан. */
export const DEFAULT_ALBUM_LAYOUT: AlbumLayout = 'slider'

/** Картинка статьи — отдельный блок figure; data-image связывает её с загруженным файлом. */
export const ArticleImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      image: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-image'),
        renderHTML: (attributes: Record<string, unknown>) => (
          attributes.image ? { 'data-image': attributes.image } : {}
        ),
      },
    }
  },
  parseHTML() {
    return [{ tag: 'img[data-image]' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['figure', { 'data-type': 'image' }, ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]]
  },
}).configure({ inline: false })

/**
 * График — неделимый блок со ссылкой на данные и подписью; в редакторе показывается его предпросмотр.
 * Правится через окно с таблицей.
 */
export function createChartNode(charts: ChartStore, label: Label) {
  return TiptapNode.create({
    name: 'chart',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    addAttributes() {
      return { chart: { default: null }, caption: { default: '' } }
    },
    parseHTML() {
      return [{
        tag: 'figure[data-type="chart"]',
        getAttrs: (element: HTMLElement) => ({
          chart: element.getAttribute('data-chart'),
          caption: element.querySelector('figcaption')?.textContent ?? '',
        }),
      }]
    },
    renderHTML({ node }) {
      return [
        'figure',
        { 'data-type': 'chart', 'data-chart': node.attrs.chart },
        ['figcaption', {}, node.attrs.caption],
      ]
    },
    addNodeView() {
      return ({ node }) => chartPreview(charts, label, node.attrs.chart, node.attrs.caption)
    },
  })
}

/** Блок графика в редакторе: предпросмотр скрыт от экранных дикторов, подпись читается. */
function chartPreview(charts: ChartStore, label: Label, id: string, caption: string) {
  const preview = el('div', { 'class': 'rich-editor-chart-preview', 'aria-hidden': 'true' })
  const dom = el(
    'figure',
    { 'class': 'rich-editor-chart', 'data-type': 'chart' },
    [preview, el('figcaption', {}, [caption])],
  )
  let view: ChartView | null = null
  let destroyed = false

  charts.get(id)
    .then(chart => drawChart(preview, chart))
    .then((drawn) => {
      view = drawn

      if (destroyed) {
        drawn.destroy()
      }
    })
    // Без данных графика показывается сообщение, подпись блока остаётся.
    .catch((error: unknown) => {
      console.error(error)
      preview.replaceWith(el('p', { class: 'rich-editor-hint' }, [label('chart_unavailable')]))
    })

  return {
    dom,
    destroy() {
      destroyed = true
      view?.destroy()
    },
  }
}

/** Альбом — ссылка на альбом и вид показа (слайдер или коллаж). Фото подставляет сервер. */
export const AlbumNode = TiptapNode.create({
  name: 'album',
  group: 'block',
  atom: true,
  draggable: true,
  selectable: true,
  addAttributes() {
    return { album: { default: null }, layout: { default: DEFAULT_ALBUM_LAYOUT }, title: { default: '' } }
  },
  parseHTML() {
    return [{
      tag: 'figure[data-type="album"]',
      getAttrs: (element: HTMLElement) => ({
        album: element.getAttribute('data-album'),
        layout: ALBUM_LAYOUTS.find(layout => layout === element.getAttribute('data-layout')) ?? DEFAULT_ALBUM_LAYOUT,
        title: element.querySelector('figcaption')?.textContent ?? '',
      }),
    }]
  },
  renderHTML({ node }) {
    const attributes = {
      'data-type': 'album',
      'data-album': node.attrs.album,
      'data-layout': node.attrs.layout,
      'class': 'rich-editor-album',
    }

    return ['figure', attributes, ['figcaption', {}, node.attrs.title]]
  },
})
