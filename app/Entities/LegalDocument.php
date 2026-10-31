<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Legal\ConsentType;
use App\Traits\HasPersistedId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Версия текста правового документа: правил сайта, согласия на обработку ПДн и т. п.
 * Версии неизменяемы: правка в админке создаёт новую версию, старые остаются для согласий, которые на них ссылаются.
 * Документ относится к региону (ru, eu, us) или ко всем регионам (region = null).
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'legal_documents')]
#[ORM\UniqueConstraint(name: 'legal_documents_version_unique', columns: ['type', 'region', 'version'])]
#[ORM\Index(name: 'legal_documents_type_region_idx', columns: ['type', 'region'])]
class LegalDocument
{
    use HasPersistedId;

    /** Длина заголовка документа — размер колонки title: заголовок выводится строкой в списке согласий. */
    public const int TITLE_LENGTH = 200;

    /** Длина текста документа в символах: политика с приложениями помещается с запасом. */
    public const int TEXT_LENGTH = 100000;

    /** Длина вида документа — размер колонки type. */
    public const int TYPE_LENGTH = 30;

    /** Длина региона — размер колонки region. */
    public const int REGION_LENGTH = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::TYPE_LENGTH, enumType: ConsentType::class)]
    private ConsentType $type;

    #[ORM\Column(type: 'string', length: self::REGION_LENGTH, nullable: true)]
    private ?string $region;

    #[ORM\Column(type: 'integer')]
    private int $version;

    #[ORM\Column(type: 'string', length: self::TITLE_LENGTH)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $text;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Новая версия документа; номер версии начинается с единицы.
     */
    public static function publish(ConsentType $type, ?string $region, int $version, string $title, string $text): self
    {
        if ($version < 1) {
            throw new InvalidArgumentException('Document version must be positive.');
        }

        $document = new self();
        $document->type = $type;
        $document->region = $region;
        $document->version = $version;
        $document->title = $title;
        $document->text = $text;
        $document->createdAt = new DateTimeImmutable();

        return $document;
    }

    /**
     * Идентификатор версии.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Вид согласия, которое подтверждает документ.
     */
    public function getType(): ConsentType
    {
        return $this->type;
    }

    /**
     * Регион документа; null — общий для всех регионов.
     */
    public function getRegion(): ?string
    {
        return $this->region;
    }

    /**
     * Номер версии.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Заголовок.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Текст документа.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Дата публикации версии.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
