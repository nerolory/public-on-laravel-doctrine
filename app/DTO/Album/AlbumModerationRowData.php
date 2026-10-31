<?php

declare(strict_types=1);

namespace App\DTO\Album;

use App\Enums\Moderation\ModerationStatus;

/**
 * Строка очереди модерации альбомов.
 */
final readonly class AlbumModerationRowData
{
    /**
     * @param string           $uuid            UUID альбома
     * @param string           $title           название
     * @param ModerationStatus $status          состояние модерации
     * @param string|null      $rejectionReason причина отклонения
     * @param int              $photos          число фото
     * @param string|null      $owner           имя владельца; null — удалён
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public ModerationStatus $status,
        public ?string $rejectionReason,
        public int $photos,
        public ?string $owner,
    ) {
    }
}
