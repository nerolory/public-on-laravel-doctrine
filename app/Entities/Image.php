<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Media\ImagePurpose;
use App\Enums\Media\StorageDriver;
use App\Traits\HasOwner;
use App\Traits\HasUuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Загруженная картинка, приведённая к общему стандарту: WebP в нескольких ширинах.
 * Наружу картинка идентифицируется UUID, числовой id используется только для связей в базе.
 */
#[ORM\Entity]
#[ORM\Table(name: 'images')]
#[ORM\Index(name: 'images_owner_idx', columns: ['owner_id'])]
class Image
{
    use HasOwner;
    use HasUuid;

    /** Длина назначения картинки — размер колонки purpose. */
    public const int PURPOSE_LENGTH = 20;

    /** Длина имени хранилища — размер колонки disk: local или s3. */
    public const int DISK_LENGTH = 20;

    /** Длина пути папки — размер колонки directory. */
    public const int DIRECTORY_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::UUID_LENGTH, unique: true)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: self::PURPOSE_LENGTH, enumType: ImagePurpose::class)]
    private ImagePurpose $purpose;

    /** Хранилище файлов: local или s3. Хранится у картинки, чтобы смена настройки не ломала старые картинки. */
    #[ORM\Column(type: 'string', length: self::DISK_LENGTH, enumType: StorageDriver::class)]
    private StorageDriver $disk;

    #[ORM\Column(type: 'string', length: self::DIRECTORY_LENGTH)]
    private string $directory;

    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    private array $widths;

    #[ORM\Embedded(class: ImageDimensions::class, columnPrefix: false)]
    private ImageDimensions $dimensions;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $owner;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Картинка, файлы которой уже записаны в хранилище.
     *
     * @param string               $uuid       UUID картинки, он же последняя часть папки
     * @param ImagePurpose         $purpose    назначение
     * @param StorageDriver        $disk       хранилище файлов
     * @param string               $directory  папка с файлами всех ширин
     * @param Collection<int, int> $widths     записанные ширины
     * @param ImageDimensions      $dimensions размеры исходника
     * @param User|null            $owner      кто загрузил картинку
     */
    public static function create(
        string $uuid,
        ImagePurpose $purpose,
        StorageDriver $disk,
        string $directory,
        Collection $widths,
        ImageDimensions $dimensions,
        ?User $owner,
    ): self {
        if ($widths->isEmpty()) {
            throw new InvalidArgumentException('An image needs at least one size.');
        }

        $image = new self();
        $image->uuid = $uuid;
        $image->purpose = $purpose;
        $image->disk = $disk;
        $image->directory = $directory;
        $image->widths = $widths->sort()->values()->all();
        $image->dimensions = $dimensions;
        $image->owner = $owner;
        $image->createdAt = new DateTimeImmutable();

        return $image;
    }

    /**
     * UUID картинки.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Назначение картинки.
     */
    public function getPurpose(): ImagePurpose
    {
        return $this->purpose;
    }

    /**
     * Хранилище файлов.
     */
    public function getDisk(): StorageDriver
    {
        return $this->disk;
    }

    /**
     * Папка с файлами всех ширин.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Записанные ширины по возрастанию.
     *
     * @return Collection<int, int>
     */
    public function getWidths(): Collection
    {
        return collect($this->widths);
    }

    /**
     * Размеры исходника.
     */
    public function getDimensions(): ImageDimensions
    {
        return $this->dimensions;
    }

    /**
     * Кто загрузил картинку; null — пользователь удалён.
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /**
     * Путь к файлу заданной ширины внутри хранилища.
     */
    public function pathFor(int $width): string
    {
        return $this->directory . '/' . $width . '.webp';
    }

    /**
     * Владелец картинки — тот, кто её загрузил.
     */
    private function owner(): ?User
    {
        return $this->owner;
    }
}
