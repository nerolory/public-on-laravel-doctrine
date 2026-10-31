<?php

declare(strict_types=1);

namespace App\DTO\Album;

use Illuminate\Support\Collection;

/**
 * Альбом из формы: название и фото по порядку.
 */
final readonly class AlbumData
{
    /**
     * @param string                         $title название альбома
     * @param Collection<int, AlbumItemData> $items фото по порядку показа
     */
    public function __construct(
        public string $title,
        public Collection $items,
    ) {
    }
}
