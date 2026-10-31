<?php

declare(strict_types=1);

namespace App\Entities;

use App\Traits\NormalizesText;
use Doctrine\ORM\Mapping as ORM;

/**
 * Заголовок и описание для поисковиков — встраиваемый объект Doctrine: его колонки с префиксом seo_
 * лежат в таблице статьи или раздела. Пустое поле означает, что значение берётся из самой записи.
 */
#[ORM\Embeddable]
final class SeoMeta
{
    use NormalizesText;

    /** Длина SEO-заголовка — размер колонки seo_title: сниппет берёт 60–70 символов, полный заголовок видят соцсети. */
    public const int TITLE_LENGTH = 255;

    /** Длина SEO-описания — размер колонки seo_description: поисковики показывают в сниппете до 320 символов. */
    public const int DESCRIPTION_LENGTH = 320;

    #[ORM\Column(name: 'title', type: 'string', length: self::TITLE_LENGTH, nullable: true)]
    private ?string $title;

    #[ORM\Column(name: 'description', type: 'string', length: self::DESCRIPTION_LENGTH, nullable: true)]
    private ?string $description;

    /**
     * Пустые и состоящие из пробелов значения сохраняются как null.
     *
     * @param string|null $title       заголовок для поисковиков
     * @param string|null $description описание для поисковиков
     */
    public function __construct(?string $title = null, ?string $description = null)
    {
        $this->title = self::blankToNull($title);
        $this->description = self::blankToNull($description);
    }

    /**
     * Свой заголовок или null.
     */
    public function title(): ?string
    {
        return $this->title;
    }

    /**
     * Своё описание или null.
     */
    public function description(): ?string
    {
        return $this->description;
    }
}
