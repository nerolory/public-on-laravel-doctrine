<?php

declare(strict_types=1);

namespace App\Services\Complaint\Targets;

use App\Contracts\ComplaintTarget;
use App\DTO\Complaint\ComplaintLabelData;
use App\Entities\Album;
use App\Entities\AlbumComplaint;
use App\Entities\Complaint;
use App\Entities\User;
use App\Enums\Complaint\ComplaintReason;
use App\Repositories\AlbumRepository;
use App\Support\Seo\SiteUrls;
use App\Traits\ChecksOwnership;
use App\Traits\HidesByRejection;
use App\Traits\ModeratorHides;
use Illuminate\Support\Collection;

/**
 * Жалобы на альбомы: на сайте альбом определяется по UUID, по решению модератора отклоняется.
 */
final class AlbumTarget implements ComplaintTarget
{
    use ChecksOwnership;
    use HidesByRejection;
    use ModeratorHides;

    /**
     * @param AlbumRepository $albums альбомы
     */
    public function __construct(
        private readonly AlbumRepository $albums,
    ) {
    }

    /**
     * Ключ цели: album.
     */
    public function key(): string
    {
        return 'album';
    }

    /**
     * Класс альбома.
     */
    public function entityClass(): string
    {
        return Album::class;
    }

    /**
     * Класс жалобы на альбом.
     */
    public function complaintClass(): string
    {
        return AlbumComplaint::class;
    }

    /**
     * Поле альбома в жалобе.
     */
    public function field(): string
    {
        return 'album';
    }

    /**
     * Опубликованный альбом по UUID.
     */
    public function findPublic(string $id): ?object
    {
        $album = $this->albums->findByUuid($id);

        return $album?->isPublished() ? $album : null;
    }

    /**
     * Альбом в любом состоянии.
     */
    public function find(int $id): ?object
    {
        return $this->albums->findById($id);
    }

    /**
     * @param Album $entity
     */
    public function complain(object $entity, User $reporter, ComplaintReason $reason, ?string $text): Complaint
    {
        return new AlbumComplaint($entity, $reporter, $reason, $text);
    }

    /**
     * Названия альбомов со ссылками по UUID.
     */
    public function labels(Collection $ids): Collection
    {
        $albums = $this->albums->titles($ids);

        return $ids->mapWithKeys(static function (int $id) use ($albums): array {
            $album = $albums->get($id);

            return [$id => $album === null
                ? new ComplaintLabelData(ComplaintLabelData::MISSING_TEXT)
                : new ComplaintLabelData($album->title, SiteUrls::albumPath($album->uuid))];
        });
    }
}
