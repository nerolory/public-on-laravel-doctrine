<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Comment\CommentExcerptData;
use App\DTO\Common\PageData;
use App\Entities\Article;
use App\Entities\Comment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Комментарии к статьям: ветки, ответы, счётчики и выдержки для админки.
 */
final class CommentRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Комментарий вместе со статьёй.
     */
    public function find(int $id): ?Comment
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c, a')
            ->from(Comment::class, 'c')
            ->join('c.article', 'a')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Ветки статьи: корневые комментарии; удалённые и скрытые остаются, только если у них есть ответы.
     *
     * @return PageData<Comment>
     */
    public function threads(Article $article, bool $popularFirst, int $page, int $limit): PageData
    {
        $query = $this->withAuthors()
            ->where('c.article = :article')
            ->andWhere('c.parent IS NULL')
            ->andWhere('(c.deletedAt IS NULL AND c.hiddenAt IS NULL) OR c.replyCount > 0')
            ->setParameter('article', $article);
        if ($popularFirst) {
            $query->orderBy('c.score', 'DESC');
        }
        $query->addOrderBy('c.id', 'DESC');

        return $this->page($query, $page, $limit);
    }

    /**
     * Видимые ответы ветки в порядке публикации.
     *
     * @return PageData<Comment>
     */
    public function replies(Comment $root, int $page, int $limit): PageData
    {
        $query = $this->withAuthors()
            ->where('c.parent = :root')
            ->andWhere('c.deletedAt IS NULL AND c.hiddenAt IS NULL')
            ->setParameter('root', $root)
            ->orderBy('c.id', 'ASC');

        return $this->page($query, $page, $limit);
    }

    /**
     * Видимые комментарии статьи для заголовка «N комментариев».
     */
    public function countVisible(Article $article): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Comment::class, 'c')
            ->join('c.author', 'u')
            ->where('c.article = :article')
            ->andWhere('c.deletedAt IS NULL AND c.hiddenAt IS NULL')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Начало текста и статья для списков комментариев в админке.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, CommentExcerptData> по идентификатору комментария
     */
    public function excerpts(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        $rows = $this->entityManager->createQueryBuilder()
            ->select('c.id, SUBSTRING(c.body, 1, ' . Comment::EXCERPT_LENGTH . ') AS body, a.id AS article, a.title')
            ->from(Comment::class, 'c')
            ->join('c.article', 'a')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->mapWithKeys(static fn (array $row): array => [(int) $row['id'] => new CommentExcerptData(
            id: (int) $row['id'],
            body: $row['body'],
            articleId: (int) $row['article'],
            articleTitle: $row['title'],
        )]);
    }

    /**
     * Сохраняет новый комментарий.
     */
    public function save(Comment $comment): void
    {
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
    }

    /**
     * Записывает изменения загруженных комментариев.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Автор и адресат ответа с аватарами одним запросом; фильтр активных пользователей убирает комментарии удалённых.
     */
    private function withAuthors(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c, u, av, rt, rav')
            ->from(Comment::class, 'c')
            ->join('c.author', 'u')
            ->leftJoin('u.avatar', 'av')
            ->leftJoin('c.replyTo', 'rt')
            ->leftJoin('rt.avatar', 'rav');
    }

    /**
     * Страница выборки: на одну запись больше, чтобы узнать о следующей странице.
     *
     * @return PageData<Comment>
     */
    private function page(QueryBuilder $query, int $page, int $limit): PageData
    {
        $rows = collect($query
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult());

        return PageData::fromOverfetched($rows, $limit);
    }
}
