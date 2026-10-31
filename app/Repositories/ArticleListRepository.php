<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Article\ArticleExportData;
use App\DTO\Article\ArticleListItemData;
use App\DTO\Article\ArticleModerationRowData;
use App\DTO\Article\ArticleStatusCountsData;
use App\DTO\Article\MyArticleData;
use App\DTO\Common\PageData;
use App\Entities\Article;
use App\Entities\PersonName;
use App\Entities\User;
use App\Enums\Article\ArticleStatus;
use App\Enums\Article\ArticleStatusFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Списки статей с анонсами и разделами: лента, страница автора, «Мои статьи», модерация и выгрузка.
 */
final class ArticleListRepository
{
    /** Начало текста для анонса: с запасом на разметку вокруг видимых символов описания. */
    private const int EXCERPT_SOURCE_LENGTH = 1000;

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Опубликованные статьи, новые первыми: лента, фильтр по разделам и страница автора.
     *
     * @param Collection<int, int>|null $sectionIds разделы с подразделами
     *
     * @return PageData<ArticleListItemData>
     */
    public function publishedPage(int $page, int $limit, ?Collection $sectionIds = null, ?User $author = null): PageData
    {
        $query = $this->listQuery('a.isPublished')
            ->where('a.isPublished = true')
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit + 1);

        if ($author !== null) {
            $query->andWhere('a.user = :author')->setParameter('author', $author);
        }

        if ($sectionIds !== null) {
            $query->andWhere(sprintf(
                'a.id IN (SELECT fa.id FROM %s fa JOIN fa.sections fs WHERE fs.id IN (:sections))',
                Article::class,
            ))->setParameter('sections', $sectionIds->all());
        }

        return $this->page(
            $query->getQuery()->getArrayResult(),
            $limit,
            static fn (array $row, Collection $sections): ArticleListItemData => new ArticleListItemData(
                id: $row['id'],
                title: $row['title'],
                excerpt: self::excerpt($row),
                isPublished: $row['isPublished'],
                sections: $sections,
            ),
        );
    }

    /**
     * Статьи автора для «Моих статей»: все, кроме архивных, последние изменённые первыми.
     *
     * @return PageData<MyArticleData>
     */
    public function authorPage(User $author, int $page, int $limit, ?ArticleStatusFilter $status = null): PageData
    {
        $query = $this->listQuery(
            'a.isPublished, a.isRejected, a.rejectionReason, a.viewCount, a.updatedAt, a.publishedAt',
        )
            ->where('a.user = :author')
            ->setParameter('author', $author)
            ->orderBy('a.updatedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit + 1);

        if ($status !== null) {
            self::whereStatus($query, $status);
        }

        return $this->page(
            $query->getQuery()->getArrayResult(),
            $limit,
            static fn (array $row, Collection $sections): MyArticleData => new MyArticleData(
                id: $row['id'],
                title: $row['title'],
                excerpt: self::excerpt($row),
                status: ArticleStatus::fromFlags($row['isPublished'], $row['isRejected']),
                rejectionReason: $row['rejectionReason'],
                viewCount: $row['viewCount'],
                updatedAt: $row['updatedAt'],
                publishedAt: $row['publishedAt'],
                sections: $sections,
            ),
        );
    }

    /**
     * Число статей автора всего и по состояниям.
     */
    public function authorCounts(User $author): ArticleStatusCountsData
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id) AS total')
            ->addSelect('SUM(CASE WHEN a.isPublished = true THEN 1 ELSE 0 END) AS published')
            ->addSelect('SUM(CASE WHEN a.isPublished = false AND a.isRejected = true THEN 1 ELSE 0 END) AS rejected')
            ->from(Article::class, 'a')
            ->where('a.user = :author')
            ->setParameter('author', $author)
            ->getQuery()
            ->getSingleResult();
        $total = (int) $row['total'];
        $published = (int) $row['published'];
        $rejected = (int) $row['rejected'];

        return new ArticleStatusCountsData(
            all: $total,
            published: $published,
            rejected: $rejected,
            pending: $total - $published - $rejected,
        );
    }

    /**
     * Страница списка модерации: все статьи, кроме архивных, новые первыми.
     *
     * @return PageData<ArticleModerationRowData>
     */
    public function moderationPage(int $page, int $limit): PageData
    {
        $rows = $this->listQuery('a.isPublished, a.isRejected, a.rejectionReason, a.viewCount')
            ->addSelect('u.email AS authorEmail')
            ->addSelect('u.fullName.firstName AS firstName, u.fullName.middleName AS middleName')
            ->addSelect('u.fullName.lastName AS lastName')
            ->leftJoin('a.user', 'u')
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
        $sections = $this->sectionNames(collect($rows)->pluck('id'));

        return new PageData(
            items: collect($rows)
                ->map(static fn (array $row): ArticleModerationRowData => self::moderationRow($row, $sections)),
            total: $this->total(),
        );
    }

    /**
     * Все статьи пользователя для выгрузки его данных.
     *
     * @return Collection<int, ArticleExportData>
     */
    public function authoredBy(User $user): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.id, a.title, a.content, a.isPublished, a.isRejected')
            ->from(Article::class, 'a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $sections = $this->sectionNames(collect($rows)->pluck('id'));

        return collect($rows)->map(static fn (array $row): ArticleExportData => new ArticleExportData(
            id: $row['id'],
            title: $row['title'],
            content: $row['content'],
            isPublished: $row['isPublished'],
            isRejected: $row['isRejected'],
            sections: $sections->get($row['id'], collect()),
        ));
    }

    /**
     * Строка списка модерации из строки выборки.
     *
     * @param array<string, mixed>                     $row
     * @param Collection<int, Collection<int, string>> $sections названия разделов по статье
     */
    private static function moderationRow(array $row, Collection $sections): ArticleModerationRowData
    {
        $status = ArticleStatus::fromFlags($row['isPublished'], $row['isRejected']);

        return new ArticleModerationRowData(
            id: $row['id'],
            title: $row['title'],
            excerpt: self::excerpt($row),
            status: $status,
            rejectionReason: $row['rejectionReason'],
            viewCount: $row['viewCount'],
            author: $row['authorEmail'] === null ? null : PersonName::displayOrEmail(
                $row['firstName'],
                $row['middleName'],
                $row['lastName'],
                $row['authorEmail'],
            ),
            sections: $sections->get($row['id'], collect()),
            currentAction: $status->moderationAction(),
        );
    }

    /**
     * Число статей, кроме архивных.
     */
    private function total(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Запрос списка: идентификатор, заголовок, данные анонса и дополнительные поля.
     */
    private function listQuery(string $fields): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a.id, a.title, a.summary')
            ->addSelect('SUBSTRING(a.content, 1, ' . self::EXCERPT_SOURCE_LENGTH . ') AS contentStart')
            ->addSelect($fields)
            ->from(Article::class, 'a');
    }

    /**
     * Страница из строк, выбранных с одной лишней: по ней видно, есть ли следующая страница.
     *
     * @template TItem
     *
     * @param list<array<string, mixed>>                                     $rows
     * @param callable(array<string, mixed>, Collection<int, string>): TItem $item
     *
     * @return PageData<TItem>
     */
    private function page(array $rows, int $limit, callable $item): PageData
    {
        $page = PageData::fromOverfetched(collect($rows), $limit);
        $sections = $this->sectionNames($page->items->pluck('id'));

        return $page->map(static fn (array $row): mixed => $item($row, $sections->get($row['id'], collect())));
    }

    /**
     * Названия разделов статей по алфавиту одним запросом.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Collection<int, string>> названия по идентификатору статьи
     */
    private function sectionNames(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.id AS article, s.name')
            ->from(Article::class, 'a')
            ->join('a.sections', 's')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return collect($rows)
            ->groupBy('article')
            ->map(static fn (Collection $group): Collection => $group->pluck('name')->values());
    }

    /**
     * Условие на состояние статьи; архивные скрыты фильтром мягкого удаления.
     */
    private static function whereStatus(QueryBuilder $query, ArticleStatusFilter $status): void
    {
        match ($status) {
            ArticleStatusFilter::Published => $query->andWhere('a.isPublished = true'),
            ArticleStatusFilter::Rejected => $query->andWhere('a.isPublished = false AND a.isRejected = true'),
            ArticleStatusFilter::Pending => $query->andWhere('a.isPublished = false AND a.isRejected = false'),
        };
    }

    /**
     * Анонс из строки запроса.
     *
     * @param array{summary: ?string, contentStart: string} $row
     */
    private static function excerpt(array $row): string
    {
        return Article::excerptOf($row['summary'], $row['contentStart']);
    }
}
