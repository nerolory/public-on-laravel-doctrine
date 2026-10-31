<?php

declare(strict_types=1);

namespace App\DTO\Album;

/**
 * Название альбома и его UUID для ссылки.
 */
final readonly class AlbumTitleData
{
    /**
     * @param int    $id    идентификатор альбома
     * @param string $uuid  UUID для адреса страницы
     * @param string $title название
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $title,
    ) {
    }
}
