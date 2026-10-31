<?php

declare(strict_types=1);

namespace App\Entities;

use App\Traits\NormalizesText;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use InvalidArgumentException;

/**
 * Тег статьи. Отдельных страниц у тегов нет: они идут в meta keywords и article:tag.
 * Бизнес-ключ — имя в нижнем регистре, поэтому «PHP» и «php» — один тег.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tags')]
#[ORM\UniqueConstraint(name: 'tags_name_key_unique', columns: ['name_key'])]
class Tag
{
    use NormalizesText;

    /** Длина тега: названия технологий и тем короче. */
    public const int MAX_LENGTH = 40;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::MAX_LENGTH)]
    private string $name;

    #[ORM\Column(name: 'name_key', type: 'string', length: self::MAX_LENGTH)]
    private string $nameKey;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    /**
     * Новый тег с нормализованным именем.
     */
    public static function create(string $name): self
    {
        $name = self::normalize($name);
        if ($name === '' || mb_strlen($name) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Tag name must be 1-' . self::MAX_LENGTH . ' characters long.');
        }
        $tag = new self();
        $tag->name = $name;
        $tag->nameKey = self::keyOf($name);

        return $tag;
    }

    /**
     * Убирает лишние пробелы; регистр, выбранный автором первым, сохраняется для показа.
     */
    public static function normalize(string $name): string
    {
        return self::collapseSpaces($name);
    }

    /**
     * Бизнес-ключ тега: имя в нижнем регистре.
     */
    public static function keyOf(string $name): string
    {
        return mb_strtolower(self::normalize($name));
    }

    /**
     * Имя тега для показа.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Бизнес-ключ тега.
     */
    public function getNameKey(): string
    {
        return $this->nameKey;
    }
}
