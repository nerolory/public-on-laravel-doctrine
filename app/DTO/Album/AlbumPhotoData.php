<?php

declare(strict_types=1);

namespace App\DTO\Album;

use App\DTO\Media\ImageData;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Фото альбома для показа: картинка и описание.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AlbumPhotoData implements Arrayable
{
    /**
     * @param ImageData   $image картинка
     * @param string|null $alt   описание фото; null, если описания нет
     */
    public function __construct(
        public ImageData $image,
        public ?string $alt,
    ) {
    }

    /**
     * Фото для ответа API.
     *
     * @return array{image: array<string, int|string>, alt: string|null}
     */
    public function toArray(): array
    {
        return ['image' => $this->image->toArray(), 'alt' => $this->alt];
    }
}
