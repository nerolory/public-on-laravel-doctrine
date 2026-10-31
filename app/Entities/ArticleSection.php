<?php

declare(strict_types=1);

namespace App\Entities;

use App\Traits\HasPersistedId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * Раздел статей. Дерево хранится вложенными множествами (Gedmo Tree): поддерево и путь читаются одним запросом.
 * Адрес раздела создаётся из названия один раз и дальше меняется только вручную.
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\Table(name: 'article_sections')]
class ArticleSection
{
    use HasPersistedId;

    /** Длина названия раздела — размер колонки name: название помещается в кнопку фильтра. */
    public const int NAME_LENGTH = 120;

    /** Длина адреса раздела — размер колонки slug: транслитерация длиннее исходного названия. */
    public const int SLUG_LENGTH = 140;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::NAME_LENGTH, unique: true)]
    private string $name;

    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    #[ORM\Column(type: 'string', length: self::SLUG_LENGTH, unique: true)]
    private string $slug;

    #[Gedmo\TreeLeft]
    #[ORM\Column(name: 'lft', type: 'integer')]
    private int $lft;

    #[Gedmo\TreeLevel]
    #[ORM\Column(name: 'lvl', type: 'integer')]
    private int $lvl;

    #[Gedmo\TreeRight]
    #[ORM\Column(name: 'rgt', type: 'integer')]
    private int $rgt;

    #[Gedmo\TreeRoot]
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'tree_root', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $root = null;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Embedded(class: SeoMeta::class, columnPrefix: 'seo_')]
    private SeoMeta $seo;

    /** @var DoctrineCollection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['lft' => 'ASC'])]
    private DoctrineCollection $children;

    private function __construct()
    {
        $this->children = new ArrayCollection();
        $this->seo = new SeoMeta();
    }

    /**
     * Новый раздел; без родителя — корневой.
     */
    public static function create(string $name, ?self $parent = null): self
    {
        $section = new self();
        $section->name = $name;
        $section->parent = $parent;

        return $section;
    }

    /**
     * Меняет название, адрес, родителя и SEO-поля. Перенос внутрь собственного поддерева проверяет сервис.
     */
    public function update(string $name, string $slug, ?self $parent, SeoMeta $seo): void
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->parent = $parent;
        $this->seo = $seo;
    }

    /**
     * SEO-поля раздела.
     */
    public function getSeo(): SeoMeta
    {
        return $this->seo;
    }

    /**
     * Адрес раздела в фильтре ленты.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Идентификатор раздела; до сохранения — null.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Название раздела.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Родительский раздел.
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Можно ли удалить раздел: подраздел — всегда, корневой — только без подразделов и статей.
     */
    public static function isDeletable(bool $root, bool $hasChildren, bool $hasArticles): bool
    {
        return ! $root || (! $hasChildren && ! $hasArticles);
    }

    /**
     * Корневой ли раздел.
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }
}
