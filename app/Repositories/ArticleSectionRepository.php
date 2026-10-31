<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\ArticleSection\SectionNodeData;
use App\DTO\ArticleSection\SectionRowData;
use App\DTO\Common\PageData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Illuminate\Support\Collection;

/**
 * Разделы статей: дерево вложенных множеств, поиск по ключам и перенос статей при удалении раздела.
 */
final class ArticleSectionRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Раздел по идентификатору.
     */
    public function find(int $id): ?ArticleSection
    {
        return $this->entityManager->find(ArticleSection::class, $id);
    }

    /**
     * Разделы по идентификаторам.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, ArticleSection>
     */
    public function findByIds(Collection $ids): Collection
    {
        return $ids->isEmpty() ? collect() : collect($this->tree()->findBy(['id' => $ids->all()]));
    }

    /**
     * Раздел по названию.
     */
    public function findByName(string $name): ?ArticleSection
    {
        return $this->tree()->findOneBy(['name' => $name]);
    }

    /**
     * Раздел по адресу.
     */
    public function findBySlug(string $slug): ?ArticleSection
    {
        return $this->tree()->findOneBy(['slug' => $slug]);
    }

    /**
     * Занято ли название другим разделом.
     */
    public function nameInUse(string $name, ?int $exceptId = null): bool
    {
        return $this->valueInUse('name', $name, $exceptId);
    }

    /**
     * Занят ли адрес другим разделом.
     */
    public function slugInUse(string $slug, ?int $exceptId = null): bool
    {
        return $this->valueInUse('slug', $slug, $exceptId);
    }

    /**
     * Все разделы деревом: корневые по алфавиту, подразделы сразу после своего родителя.
     *
     * @return Collection<int, SectionNodeData>
     */
    public function all(): Collection
    {
        return collect($this->treeOrdered()->getQuery()->getArrayResult())->map(self::node(...));
    }

    /**
     * Страница списка разделов в админке с числом статей.
     *
     * @return PageData<SectionRowData>
     */
    public function page(int $page, int $limit): PageData
    {
        $rows = $this->treeOrdered()
            ->addSelect(sprintf(
                '(SELECT COUNT(la.id) FROM %s la JOIN la.sections ls WHERE ls.id = s.id) AS articleCount',
                Article::class,
            ))
            ->addSelect('(s.rgt - s.lft) AS span')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
        $total = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(ArticleSection::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        return new PageData(
            items: collect($rows)->map(static function (array $row): SectionRowData {
                $node = self::node($row);
                // У листа дерева rgt - lft = 1: больше — есть подразделы.
                $hasChildren = (int) $row['span'] > 1;

                return new SectionRowData(
                    node: $node,
                    articleCount: (int) $row['articleCount'],
                    hasChildren: $hasChildren,
                    deletable: ArticleSection::isDeletable(
                        root: $node->level === 0,
                        hasChildren: $hasChildren,
                        hasArticles: (int) $row['articleCount'] > 0,
                    ),
                );
            }),
            total: (int) $total,
        );
    }

    /**
     * Идентификаторы раздела и всех его подразделов.
     *
     * @return Collection<int, int>
     */
    public function subtreeIds(ArticleSection $section): Collection
    {
        $ids = $this->tree()
            ->getChildrenQueryBuilder($section, direct: false, sortByField: null, direction: 'ASC', includeNode: true)
            ->select('node.id')
            ->getQuery()
            ->getSingleColumnResult();

        return collect($ids)->map(static fn (mixed $id): int => (int) $id);
    }

    /**
     * Путь от корня до раздела включительно.
     *
     * @return Collection<int, ArticleSection>
     */
    public function path(ArticleSection $section): Collection
    {
        return collect($this->tree()->getPath($section));
    }

    /**
     * Есть ли у раздела подразделы.
     */
    public function hasChildren(ArticleSection $section): bool
    {
        return $this->tree()->childCount($section, direct: true) > 0;
    }

    /**
     * Есть ли в разделе статьи.
     */
    public function hasArticles(ArticleSection $section): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->join('a.sections', 's')
            ->where('s = :section')
            ->setParameter('section', $section)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Сохраняет раздел; подразделы родителя упорядочиваются по названию.
     */
    public function save(ArticleSection $section): void
    {
        $this->entityManager->persist($section);
        $this->entityManager->flush();

        $parent = $section->getParent();
        if ($parent !== null) {
            $this->tree()->reorder($parent, 'name', 'ASC', verify: false, recursive: false);
        }
    }

    /**
     * Удаляет раздел: его статьи получают родительский раздел, подразделы поднимаются к родителю.
     */
    public function delete(ArticleSection $section): void
    {
        $links = $this->entityManager->getClassMetadata(Article::class)
            ->getAssociationMapping('sections')->joinTable->name;
        $parent = $section->getParent();

        $this->entityManager->wrapInTransaction(function () use ($links, $section, $parent): void {
            $connection = $this->entityManager->getConnection();
            if ($parent !== null) {
                $connection->executeStatement(
                    "INSERT INTO {$links} (article_id, section_id)
                     SELECT l.article_id, :parent FROM {$links} l
                     WHERE l.section_id = :section
                       AND NOT EXISTS (
                         SELECT 1 FROM {$links} x WHERE x.article_id = l.article_id AND x.section_id = :parent
                     )",
                    ['parent' => $parent->getId(), 'section' => $section->getId()],
                );
            }
            $connection->executeStatement(
                "DELETE FROM {$links} WHERE section_id = :section",
                ['section' => $section->getId()],
            );
            $this->tree()->removeFromTree($section);
        });
        // Связи статей с разделами правятся сырым SQL в обход ORM: загруженные сущности устарели, их сбрасываем.
        $this->entityManager->clear();
    }

    /**
     * Занято ли значение поля другим разделом.
     */
    private function valueInUse(string $field, string $value, ?int $exceptId): bool
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(ArticleSection::class, 's')
            ->where("s.{$field} = :value")
            ->setParameter('value', $value);
        if ($exceptId !== null) {
            $query->andWhere('s.id <> :id')->setParameter('id', $exceptId);
        }

        return (int) $query->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Корневые разделы по алфавиту, подразделы сразу после своего родителя.
     */
    private function treeOrdered(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s.id, s.name, s.slug, s.lvl AS level, p.slug AS parent')
            ->from(ArticleSection::class, 's')
            ->join('s.root', 'r')
            ->leftJoin('s.parent', 'p')
            ->orderBy('r.name', 'ASC')
            ->addOrderBy('s.lft', 'ASC');
    }

    /**
     * Репозиторий дерева Gedmo.
     *
     * @return NestedTreeRepository<ArticleSection>
     */
    private function tree(): NestedTreeRepository
    {
        /** @var NestedTreeRepository<ArticleSection> */
        return $this->entityManager->getRepository(ArticleSection::class);
    }

    /**
     * Раздел из строки запроса дерева.
     *
     * @param array{id: int, name: string, slug: string, level: int, parent: ?string} $row
     */
    private static function node(array $row): SectionNodeData
    {
        return new SectionNodeData(
            id: (int) $row['id'],
            name: $row['name'],
            slug: $row['slug'],
            level: (int) $row['level'],
            parent: $row['parent'],
        );
    }
}
