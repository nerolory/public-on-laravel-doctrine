import { apiPaths } from '../../shared/utils/api-paths'
import { imageForm } from '../../shared/utils/images'
import { isList, isRecord, isStringList } from '../../shared/utils/guards'
import type { PublicationStatus } from '../../shared/types/content'
import type { AlbumSummary, ChartPayload, ChartResult, ChartType, RichEditorApi, UploadedImage } from './types'

/** Отправка запросов страницы, на которой работает редактор: ответы приходят без проверки и проверяются здесь. */
type EditorTransport = {
  get: (path: string) => Promise<unknown>
  post: (path: string, body: Record<string, unknown> | FormData) => Promise<unknown>
}

/** Запросы редактора к серверу: одинаковые на сайте и в админке, отличается только способ отправки. */
export function createEditorApi(transport: EditorTransport): RichEditorApi {
  return {
    uploadImage: async (file, purpose) => checked(
      await transport.post(apiPaths.images, imageForm(file, purpose)),
      isUploadedImage,
    ),
    createChart: async (data: ChartPayload) => checked(await transport.post(apiPaths.charts, data), isChartResult),
    loadChart: async id => checked(await transport.get(apiPaths.chart(id)), isChartResult),
    listAlbums: async () => checked(await transport.get(apiPaths.myAlbums), isAlbumList).items,
  }
}

/** Ответ сервера, не совпавший с ожидаемым видом, даёт ошибку и не попадает в редактор. */
function checked<T>(value: unknown, isExpected: (value: unknown) => value is T): T {
  if (!isExpected(value)) {
    throw new TypeError('Ответ сервера не совпадает с ожидаемым видом')
  }

  return value
}

function isUploadedImage(value: unknown): value is UploadedImage {
  return isRecord(value)
    && typeof value.id === 'string'
    && typeof value.url === 'string'
    && typeof value.srcset === 'string'
    && typeof value.width === 'number'
    && typeof value.height === 'number'
}

function isChartType(value: unknown): value is ChartType {
  return value === 'bar' || value === 'line' || value === 'pie'
}

function isChartSeries(value: unknown): value is ChartPayload['series'][number] {
  return isRecord(value) && typeof value.name === 'string' && isNumberList(value.values)
}

function isChartResult(value: unknown): value is ChartResult {
  return isRecord(value)
    && typeof value.id === 'string'
    && isChartType(value.type)
    && typeof value.title === 'string'
    && isStringList(value.labels)
    && isList(value.series)
    && value.series.every(isChartSeries)
}

function isPublicationStatus(value: unknown): value is PublicationStatus {
  return value === 'pending' || value === 'published' || value === 'rejected'
}

function isAlbumSummary(value: unknown): value is AlbumSummary {
  return isRecord(value)
    && typeof value.id === 'string'
    && typeof value.title === 'string'
    && isPublicationStatus(value.status)
}

function isAlbumList(value: unknown): value is { items: AlbumSummary[] } {
  return isRecord(value) && isList(value.items) && value.items.every(isAlbumSummary)
}

function isNumberList(value: unknown): value is number[] {
  return isList(value) && value.every(item => typeof item === 'number')
}
