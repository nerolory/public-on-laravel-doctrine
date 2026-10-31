<?php

declare(strict_types=1);

namespace App\DTO\Album;

use App\DTO\User\UserCardData;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Страница альбома: альбом, владелец и права посетителя.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PublicAlbumData implements Arrayable
{
    /**
     * @param AlbumViewData     $album       альбом
     * @param UserCardData|null $owner       владелец; null — удалён
     * @param bool              $canEdit     посетитель может править альбом
     * @param bool              $canModerate посетитель может модерировать
     */
    public function __construct(
        public AlbumViewData $album,
        public ?UserCardData $owner,
        public bool $canEdit,
        public bool $canModerate,
    ) {
    }

    /**
     * Альбом для ответа API с владельцем и правами.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->album->toArray() + [
            'owner' => $this->owner?->reference(),
            'permissions' => ['edit' => $this->canEdit, 'moderate' => $this->canModerate],
        ];
    }
}
