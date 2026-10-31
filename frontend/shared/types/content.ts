/** Статус модерации статьи или альбома. */
export type PublicationStatus = 'pending' | 'published' | 'rejected'

/** Вид показа альбома в статье. */
export type AlbumLayout = 'slider' | 'collage'

/** Страница списка: элементы и признак следующей страницы. */
export type ListPage<T> = { items: T[], has_more: boolean }

/** Картинка для показа: адрес, набор размеров и пропорции. */
export type ImageView = { url: string, srcset: string, width: number, height: number }
