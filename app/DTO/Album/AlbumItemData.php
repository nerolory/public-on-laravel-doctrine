<?php

declare(strict_types=1);

namespace App\DTO\Album;

/**
 * Фото альбома из формы: UUID загруженной картинки и описание.
 */
final readonly class AlbumItemData
{
    /**
     * @param string      $image UUID картинки с назначением album
     * @param string|null $alt   описание фото для незрячих; null, если описания нет
     */
    public function __construct(
        public string $image,
        public ?string $alt,
    ) {
    }
}
