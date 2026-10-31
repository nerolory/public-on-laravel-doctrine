<?php

declare(strict_types=1);

namespace App\Entities;

use App\Contracts\Moderatable;
use App\DTO\Album\AlbumData;
use App\DTO\Album\AlbumItemData;
use App\Enums\Moderation\ModerationStatus;
use App\Traits\HasOwner;
use App\Traits\HasPersistedId;
use App\Traits\HasUuid;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LaravelDoctrine\ORM\Contracts\UrlRoutable;

/**
 * Альбом: фотографии с подписями, проходит модерацию. Встраивается в статьи
 * блоком data-album; на сайте показывается, только пока опубликован.
 */
#[ORM\Entity]
#[ORM\Table(name: 'albums')]
#[ORM\UniqueConstraint(name: 'albums_uuid_unique', columns: ['uuid'])]
#[ORM\Index(name: 'albums_status_idx', columns: ['status', 'id'])]
class Album implements Moderatable, UrlRoutable
{
    use HasOwner;
    use HasPersistedId;
    use HasUuid;

    /** Длина названия альбома — размер колонки title: название помещается в строку списка. */
    public const int TITLE_LENGTH = 120;

    /** Длина статуса модерации — размер колонки status. */
    public const int STATUS_LENGTH = 16;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::UUID_LENGTH)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: 'string', length: self::TITLE_LENGTH)]
    private string $title;

    #[ORM\Column(
        type: 'string',
        length: self::STATUS_LENGTH,
        enumType: ModerationStatus::class,
        options: ['default' => 'pending'],
    )]
    private ModerationStatus $status = ModerationStatus::Pending;

    #[ORM\Column(name: 'rejection_reason', type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    /** @var DoctrineCollection<int, AlbumItem> */
    #[ORM\OneToMany(
        targetEntity: AlbumItem::class,
        mappedBy: 'album',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private DoctrineCollection $items;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        $this->items = new ArrayCollection();
    }

    /**
     * Новый альбом на модерации.
     *
     * @param string                    $uuid   UUID альбома
     * @param User                      $owner  владелец
     * @param AlbumData                 $data   название и фото из формы
     * @param Collection<string, Image> $images картинки фото по UUID
     */
    public static function create(string $uuid, User $owner, AlbumData $data, Collection $images): self
    {
        $album = new self();
        $album->uuid = $uuid;
        $album->owner = $owner;
        $album->revise($data, $images);

        return $album;
    }

    /**
     * Правка владельцем: новые фото и подписи, альбом снова уходит на модерацию.
     *
     * @param Collection<string, Image> $images картинки фото по UUID
     */
    public function revise(AlbumData $data, Collection $images): void
    {
        if ($data->items->isEmpty()) {
            throw new InvalidArgumentException('An album needs at least one image.');
        }

        $this->title = $data->title;
        $this->items->clear();
        $data->items->values()->each(function (AlbumItemData $item, int $position) use ($images): void {
            $image = $images->get($item->image)
                ?? throw new InvalidArgumentException('Unknown album image: ' . $item->image);
            $this->items->add(AlbumItem::create($this, $image, $item->alt, $position));
        });
        $this->sendToModeration();
    }

    /**
     * Публикует альбом.
     */
    public function publish(): void
    {
        $this->status = ModerationStatus::Published;
        $this->rejectionReason = null;
    }

    /**
     * Отклоняет альбом с причиной для владельца.
     */
    public function reject(string $reason): void
    {
        $reason = ModerationStatus::normalizeReason($reason);

        $this->status = ModerationStatus::Rejected;
        $this->rejectionReason = $reason;
    }

    /**
     * Возвращает альбом на модерацию.
     */
    public function sendToModeration(): void
    {
        $this->status = ModerationStatus::Pending;
        $this->rejectionReason = null;
    }

    /**
     * Опубликован ли альбом.
     */
    public function isPublished(): bool
    {
        return $this->status === ModerationStatus::Published;
    }

    /**
     * Идентификатор альбома.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Ключ записи в адресе: UUID.
     */
    public static function getRouteKeyNameStatic(): string
    {
        return 'uuid';
    }

    /**
     * UUID альбома.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Владелец.
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * Название.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Состояние модерации.
     */
    public function getStatus(): ModerationStatus
    {
        return $this->status;
    }

    /**
     * Причина отклонения.
     */
    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /**
     * Фото по порядку показа.
     *
     * @return Collection<int, AlbumItem>
     */
    public function getItems(): Collection
    {
        return collect($this->items->getValues());
    }

    /**
     * Владелец альбома.
     */
    private function owner(): User
    {
        return $this->owner;
    }
}
