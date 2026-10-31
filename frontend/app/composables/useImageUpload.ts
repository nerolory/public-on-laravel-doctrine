import type { UploadedImage } from '~~/editor/rich-editor'
import type { ImagePurpose } from '#shared/utils/images'

/** Загрузка картинки для статьи, обложки или альбома от имени текущего пользователя. */
export function useImageUpload() {
  const { submit } = useAccount()

  return (file: File, purpose: ImagePurpose) => submit<UploadedImage>(apiPaths.images, 'POST', imageForm(file, purpose))
}
