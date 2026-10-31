<?php

declare(strict_types=1);

namespace App\DTO\Album;

use App\Enums\Moderation\ModerationStatus;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Альбом для показа: название, состояние модерации и фото.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AlbumViewData implements Arrayable
{
    /**
     * @param string                          $id              UUID альбома
     * @param string                          $title           название
     * @param ModerationStatus                $status          состояние модерации
     * @param string|null                     $rejectionReason причина отклонения
     * @param Collection<int, AlbumPhotoData> $items           фото по порядку
     */
    public function __construct(
        public string $id,
        public string $title,
        public ModerationStatus $status,
        public ?string $rejectionReason,
        public Collection $items,
    ) {
    }

    /**
     * Альбом для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejectionReason,
            'items' => $this->items->toArray(),
        ];
    }
}
