<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\DTO\Media\ImageData;
use App\Entities\Image;
use App\Entities\ImageDimensions;
use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Repositories\ImageRepository;
use App\Support\Media\MediaStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use SplFileInfo;
use Throwable;

/**
 * Приводит загруженные картинки к общему стандарту: поворот по EXIF, удаление метаданных,
 * WebP в наборе ширин, зависящем от назначения.
 */
final class ImageService
{
    /** Качество WebP: на фотографиях отличие от исходника незаметно, файл в несколько раз меньше. */
    private const int QUALITY = 80;

    /**
     * @param ImageManagerInterface $images     чтение и преобразование картинок
     * @param MediaStorage          $storage    хранилище медиафайлов
     * @param ImageRepository       $repository записи о картинках
     */
    public function __construct(
        private readonly ImageManagerInterface $images,
        private readonly MediaStorage $storage,
        private readonly ImageRepository $repository,
    ) {
    }

    /**
     * Записывает все ширины картинки и сохраняет её; при ошибке записанные файлы удаляются.
     */
    public function store(SplFileInfo $file, ImagePurpose $purpose, ?User $owner): Image
    {
        // Поворот по EXIF выполняется при чтении: менеджер создан с autoOrientation (AppServiceProvider).
        $source = $this->images->decodePath($file->getPathname());
        if ($purpose->isSquare()) {
            $side = min($source->width(), $source->height());
            $source->cover($side, $side);
        }

        $uuid = (string) Str::uuid();
        $driver = $this->storage->currentDriver();
        $disk = $this->storage->disk($driver);
        $directory = $purpose->value . '/' . date('Y/m') . '/' . $uuid;
        $widths = $this->widths($purpose, $source->width());
        $written = [];

        try {
            foreach ($widths as $width) {
                $written[] = $this->writeVariant($disk, $source, $directory, $width);
            }
            $dimensions = new ImageDimensions($source->width(), $source->height());
            $image = Image::create($uuid, $purpose, $driver, $directory, collect($widths), $dimensions, $owner);
            $this->repository->save($image);
        } catch (Throwable $exception) {
            $disk->delete($written);

            throw $exception;
        }

        return $image;
    }

    /**
     * Удаляет файлы и запись о картинке.
     */
    public function delete(Image $image): void
    {
        $this->storage->disk($image->getDisk())->deleteDirectory($image->getDirectory());
        $this->repository->delete($image);
    }

    /**
     * Картинка для API и редактора: основной файл ближайшей подходящей ширины и полный srcset.
     *
     * @param int|null $preferredWidth нужная ширина; null — самая большая
     */
    public function present(Image $image, ?int $preferredWidth = null): ImageData
    {
        $widths = $image->getWidths();
        $main = $preferredWidth === null
            ? $widths->last()
            : $widths->first(static fn (int $width): bool => $width >= $preferredWidth, $widths->last());
        $srcset = $widths->map(fn (int $width): string => $this->storage->url(
            $image->getDisk(),
            $image->pathFor($width),
        ) . ' ' . $width . 'w');

        return new ImageData(
            id: $image->getUuid(),
            url: $this->storage->url($image->getDisk(), $image->pathFor($main)),
            srcset: $srcset->implode(', '),
            width: $main,
            height: $image->getDimensions()->heightFor($main),
        );
    }

    /**
     * Картинка в нужной ширине или null, если картинки нет.
     */
    public function presentOrNull(?Image $image, int $preferredWidth): ?ImageData
    {
        return $image === null ? null : $this->present($image, $preferredWidth);
    }

    /**
     * Ширины назначения не больше исходника; маленький исходник сохраняется в своей ширине.
     *
     * @return list<int>
     */
    private function widths(ImagePurpose $purpose, int $sourceWidth): array
    {
        $widths = $purpose->widths()->filter(static fn (int $width): bool => $width <= $sourceWidth)->values()->all();

        return $widths === [] ? [$sourceWidth] : $widths;
    }

    /**
     * Записывает уменьшенную копию в WebP и возвращает путь к файлу.
     */
    private function writeVariant(Filesystem $disk, ImageInterface $source, string $directory, int $width): string
    {
        $path = $directory . '/' . $width . '.webp';
        $variant = (clone $source)->scaleDown(width: $width);
        $disk->put($path, (string) $variant->encode(new WebpEncoder(quality: self::QUALITY, strip: true)));

        return $path;
    }
}
