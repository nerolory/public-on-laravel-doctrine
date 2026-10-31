<?php

declare(strict_types=1);

namespace App\Services\Album;

use App\DTO\Album\AlbumData;
use App\DTO\Album\AlbumItemData;
use App\DTO\Album\AlbumModerationRowData;
use App\DTO\Album\AlbumPhotoData;
use App\DTO\Album\AlbumViewData;
use App\DTO\Album\PublicAlbumData;
use App\DTO\Common\PageData;
use App\DTO\User\UserCardData;
use App\Entities\Album;
use App\Entities\AlbumItem;
use App\Entities\Image;
use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Enums\Moderation\ModerationAction;
use App\Repositories\AlbumRepository;
use App\Repositories\ImageRepository;
use App\Services\Media\ImageService;
use App\Services\User\PermissionService;
use App\Support\Settings\PageSizes;
use App\Traits\RequiresAllFound;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Альбомы: создание и правка владельцем, модерация, показ на сайте.
 */
final class AlbumService
{
    use RequiresAllFound;

    /**
     * @param AlbumRepository   $albums       альбомы
     * @param ImageRepository   $images       загруженные картинки
     * @param ImageService      $imageService адреса картинок
     * @param PermissionService $permissions  права модераторов
     * @param PageSizes         $pageSizes    размеры списков из настроек
     */
    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly ImageRepository $images,
        private readonly ImageService $imageService,
        private readonly PermissionService $permissions,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Новый альбом на модерации; фото проверил StoreAlbumRequest.
     *
     * @throws ValidationException фото удалили после проверки формы
     */
    public function create(AlbumData $data, User $owner): Album
    {
        $album = Album::create((string) Str::uuid(), $owner, $data, $this->imagesOf($data));
        $this->albums->save($album);

        return $album;
    }

    /**
     * Правка владельцем возвращает альбом на модерацию.
     *
     * @throws ValidationException фото удалили после проверки формы
     */
    public function update(Album $album, AlbumData $data): Album
    {
        $album->revise($data, $this->imagesOf($data));
        $this->albums->save($album);

        return $album;
    }

    /**
     * Удаляет альбом владельца.
     */
    public function delete(Album $album): void
    {
        $this->albums->delete([$album]);
    }

    /**
     * Альбом для страницы; кто его видит, решает AlbumPolicy.
     */
    public function show(Album $album, ?User $viewer): PublicAlbumData
    {
        $isOwner = $viewer !== null && $album->isOwnedBy($viewer);
        $canModerate = $viewer !== null && $this->permissions->canModerate($viewer);
        $owner = $album->getOwner();

        return new PublicAlbumData(
            album: $this->present($album, withModeration: $isOwner || $canModerate),
            owner: $owner->isDeleted()
                ? null
                : new UserCardData((int) $owner->getAuthIdentifier(), $owner->publicName()),
            canEdit: $isOwner && ! $viewer->isBanned(),
            canModerate: $canModerate,
        );
    }

    /**
     * Страница «Моих альбомов».
     *
     * @return PageData<AlbumViewData>
     */
    public function ownedBy(User $owner, int $page): PageData
    {
        return $this->albums->ownedBy($owner, $page, $this->pageSizes->albums())
            ->map(fn (Album $album): AlbumViewData => $this->present($album, withModeration: true));
    }

    /**
     * Страница очереди модерации.
     *
     * @return PageData<AlbumModerationRowData>
     */
    public function moderationPage(int $page): PageData
    {
        return $this->albums->moderationPage($page, $this->pageSizes->admin());
    }

    /**
     * Действие модератора над несколькими альбомами.
     *
     * @param Collection<int, string> $uuids
     *
     * @throws EntityNotFoundException хотя бы одного альбома нет
     */
    public function moderate(Collection $uuids, ModerationAction $action, ?string $reason): void
    {
        $albums = $this->albums->findForModeration($uuids);
        $this->requireAllFound(
            $uuids,
            $albums->map(static fn (Album $album): string => $album->getUuid()),
            Album::class,
            'uuid',
        );

        $action->perform($albums, $this->albums, $reason);
    }

    /**
     * Альбом для API; причину отклонения видят только владелец и модераторы.
     */
    public function present(Album $album, bool $withModeration): AlbumViewData
    {
        return new AlbumViewData(
            id: $album->getUuid(),
            title: $album->getTitle(),
            status: $album->getStatus(),
            rejectionReason: $withModeration ? $album->getRejectionReason() : null,
            items: $album->getItems()->map(fn (AlbumItem $item): AlbumPhotoData => new AlbumPhotoData(
                image: $this->imageService->present($item->getImage(), ImagePurpose::PAGE_WIDTH),
                alt: $item->getAlt(),
            )),
        );
    }

    /**
     * Картинки фото альбома по UUID.
     *
     * @return Collection<string, Image> картинки по UUID
     *
     * @throws ValidationException фото удалили после проверки формы
     */
    private function imagesOf(AlbumData $data): Collection
    {
        $images = $this->images->findByUuids($this->imageUuidsOf($data));
        $data->items->values()->each(static function (AlbumItemData $item, int $index) use ($images): void {
            if (! $images->has($item->image)) {
                throw ValidationException::withMessages([
                    "items.{$index}.image" => __('messages.albums.image_invalid'),
                ]);
            }
        });

        return $images;
    }

    /**
     * UUID всех картинок альбома без повторов.
     *
     * @return Collection<int, string>
     */
    private function imageUuidsOf(AlbumData $data): Collection
    {
        return $data->items->map(static fn (AlbumItemData $item): string => $item->image)->unique()->values();
    }
}
