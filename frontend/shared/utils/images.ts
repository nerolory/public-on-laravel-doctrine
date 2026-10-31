/** Форматы картинок, которые принимает загрузка для статей, обложек и альбомов. */
export const IMAGE_TYPES = 'image/jpeg,image/png,image/webp,image/gif'

/** Форматы аватара: анимация в аватаре не поддерживается, поэтому без GIF. */
export const AVATAR_IMAGE_TYPES = 'image/jpeg,image/png,image/webp'

/**
 * Атрибуты sizes картинок: ширина, под которую браузер выбирает файл из srcset. Значения не меньше ширины картинки
 * в раскладке из app/assets/scss/_variables.scss, поэтому картинка остаётся чёткой.
 */
export const IMAGE_SIZES = {
  /** Крупный аватар профиля и карточки автора: $avatar-size-large. */
  avatarLarge: '96px',
  /** Аватар в меню учётной записи: $avatar-size-medium. */
  avatarMedium: '40px',
  /** Миниатюра фото в форме альбома: $thumb-width-lg; на узком экране миниатюра меньше. */
  albumFormThumb: '120px',
  /** Миниатюра в списке «Мои альбомы»: $thumb-width-md. */
  albumListThumb: '96px',
  /** Обложка в форме статьи: колонка формы $width-form, на узком экране — не шире экрана. */
  coverPreview: '(max-width: 560px) 100vw, 490px',
  /** Фото на странице альбома: колонка сетки от $width-card-wide-min, на узком экране — не шире экрана. */
  albumPhoto: '(max-width: 800px) 100vw, 400px',
  /** Обложка статьи: колонка чтения $width-reading, на узком экране — во всю ширину. */
  articleCover: '(max-width: 800px) 100vw, 800px',
} as const

/** Назначение загружаемой картинки: от него зависят размеры, которые готовит сервер. */
export type ImagePurpose = 'content' | 'cover' | 'album'

/** Тело запроса загрузки картинки; без назначения — для аватара. */
export function imageForm(file: File, purpose?: ImagePurpose): FormData {
  const body = new FormData()

  body.append('file', file)

  if (purpose) {
    body.append('purpose', purpose)
  }

  return body
}

/** Файлы, выбранные в поле type="file". Для такого поля список файлов всегда есть; его отсутствие — ошибка разметки. */
export function selectedFiles(input: { files: ArrayLike<File> | null }): File[] {
  if (!input.files) {
    throw new Error('Элемент не является полем выбора файлов')
  }

  return Array.from(input.files)
}
