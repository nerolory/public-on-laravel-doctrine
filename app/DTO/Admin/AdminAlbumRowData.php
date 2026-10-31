<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use Illuminate\Support\Collection;

/**
 * Строка модерации альбома в админке.
 */
final readonly class AdminAlbumRowData
{
    /**
     * @param string                           $uuid            идентификатор альбома
     * @param string                           $title           название
     * @param string                           $photosLabel     число фотографий с склонением
     * @param string                           $ownerName       имя владельца или подпись удалённого автора
     * @param string                           $status          текущее действие модерации
     * @param string|null                      $rejectionReason причина отклонения, если альбом отклонён
     * @param string                           $openUrl         публичный адрес альбома
     * @param Collection<int, AdminOptionData> $statusOptions   варианты статуса для выбора
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $photosLabel,
        public string $ownerName,
        public string $status,
        public ?string $rejectionReason,
        public string $openUrl,
        public Collection $statusOptions,
    ) {
    }
}
