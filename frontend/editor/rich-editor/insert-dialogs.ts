import { NodeSelection } from '@tiptap/pm/state'
import { IMAGE_TYPES, selectedFiles } from '../../shared/utils/images'
import { chartGrid } from './chart-grid'
import { openDialog } from './dialog'
import type { DialogOptions } from './dialog'
import { el, field, input, select } from './dom'
import { ALBUM_LAYOUTS, DEFAULT_ALBUM_LAYOUT } from './nodes'
import type { AlbumAttributes, AlbumSummary, BlockNode, ChartPayload, EditorContext } from './types'

/** Новый график: столбчатый, две пустые строки и один ряд. */
const EMPTY_CHART: ChartPayload = { type: 'bar', title: '', labels: ['', ''], series: [{ name: '', values: [0, 0] }] }

/** Окна вставки ссылки, картинки, графика и альбома. */
export function insertDialogs(context: EditorContext) {
  const { editor, chain, label, api, limits, charts, say, errorText } = context

  /** Окно с общими для редактора кнопкой отмены и возвратом фокуса. */
  const open = (options: Omit<DialogOptions, 'cancel' | 'onClose'>) =>
    openDialog({ ...options, cancel: label('cancel'), onClose: () => editor.commands.focus() })

  /** Выделенный блок данного типа: окно правит его на месте. */
  const selectedNode = (type: string) => {
    const selection = editor.state.selection

    return selection instanceof NodeSelection && selection.node.type.name === type ? selection : null
  }

  /** Блок вставляется с пустым абзацем после него: следующая вставка не заменит выделенный блок. */
  const place = (node: BlockNode, selection: NodeSelection | null) => {
    if (selection) {
      chain().setNodeSelection(selection.from).insertContent(node).run()

      return
    }

    chain().insertContent([node, { type: 'paragraph' }]).run()
  }

  const imageCount = () => {
    let count = 0

    editor.state.doc.descendants((node) => {
      if (node.type.name === 'image') {
        count++
      }
    })

    return count
  }

  return {
    link() {
      const url = input({
        type: 'url',
        value: editor.getAttributes('link').href ?? '',
        required: true,
        placeholder: 'https://',
      })
      const remove = { text: label('link_remove'), run: () => chain().extendMarkRange('link').unsetLink().run() }

      open({
        title: label('link_title'),
        fields: [field(label('link_url'), url)],
        submit: label('save'),
        extra: editor.isActive('link') ? [remove] : [],
        onSubmit: async () => {
          chain().extendMarkRange('link').setLink({ href: url.value.trim() }).run()
        },
      })
    },

    image() {
      if (imageCount() >= limits.images) {
        say(label('too_many_images', { max: limits.images }))

        return
      }

      const file = input({ type: 'file', accept: IMAGE_TYPES, required: true })
      const alt = input({ type: 'text', maxlength: limits.image_alt })

      open({
        title: label('image_title'),
        fields: [field(label('image_file'), file), field(label('image_alt'), alt)],
        submit: label('insert'),
        onSubmit: async () => {
          const chosen = selectedFiles(file)[0]

          if (!chosen) {
            return label('upload_failed')
          }

          say(label('uploading'))

          try {
            const image = await api.uploadImage(chosen, 'content')

            place(
              {
                type: 'image',
                attrs: {
                  src: image.url,
                  alt: alt.value.trim(),
                  width: image.width,
                  height: image.height,
                  image: image.id,
                },
              },
              null,
            )
          } catch (error) {
            return errorText(error, label('upload_failed'))
          } finally {
            say('')
          }
        },
      })
    },

    async chart() {
      const selection = selectedNode('chart')
      let initial: ChartPayload

      try {
        initial = selection ? await charts.get(selection.node.attrs.chart) : EMPTY_CHART
      } catch (error) {
        say(errorText(error, label('chart_unavailable')))

        return
      }

      const grid = chartGrid(initial, label, limits)

      open({
        title: label('chart_title'),
        fields: grid.fields,
        submit: label(selection ? 'save' : 'insert'),
        wide: true,
        onSubmit: async () => {
          try {
            const chart = await api.createChart(grid.value())

            charts.put(chart)
            place({ type: 'chart', attrs: { chart: chart.id, caption: chart.title } }, selection)
          } catch (error) {
            return errorText(error, label('chart_failed'))
          }
        },
      })
    },

    async album() {
      let albums: AlbumSummary[]

      try {
        albums = await api.listAlbums()
      } catch (error) {
        say(errorText(error, label('album_failed')))

        return
      }

      const selection = selectedNode('album')
      const first = albums[0]

      if (!first) {
        say(label('album_none'))

        return
      }

      const current: Partial<AlbumAttributes> = selection?.node.attrs ?? {}
      const title = (album: AlbumSummary) => album.status === 'published'
        ? album.title
        : `${album.title} (${label(`album_${album.status}`)})`
      const choice = select(
        albums.map(album => ({ value: album.id, text: title(album) })),
        current.album ?? first.id,
        { required: '' },
      )
      const layout = select(
        ALBUM_LAYOUTS.map(value => ({ value, text: label(`album_${value}`) })),
        current.layout ?? DEFAULT_ALBUM_LAYOUT,
      )

      open({
        title: label('album_title'),
        fields: [
          field(label('album_choose'), choice),
          field(label('album_layout'), layout),
          el('p', { class: 'rich-editor-hint' }, [label('album_hint')]),
        ],
        submit: label(selection ? 'save' : 'insert'),
        onSubmit: async () => {
          const album = albums.find(item => item.id === choice.value)

          if (album) {
            place({ type: 'album', attrs: { album: album.id, layout: layout.value, title: album.title } }, selection)
          }
        },
      })
    },
  }
}
