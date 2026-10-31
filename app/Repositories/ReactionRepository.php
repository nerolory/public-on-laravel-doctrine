<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Reaction\ConflictScoreData;
use App\DTO\Reaction\ReactionSummaryData;
use App\Entities\Article;
use App\Entities\ArticleReaction;
use App\Entities\Comment;
use App\Entities\CommentReaction;
use App\Entities\Reaction;
use App\Entities\User;
use App\Enums\Reaction\ReactionType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Реакции на статьи и комментарии: поиск, сводки и рейтинг конфликтности.
 */
final class ReactionRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Реакция пользователя данного типа на статью.
     */
    public function findForArticle(Article $article, User $user, ReactionType $type): ?ArticleReaction
    {
        return $this->entityManager->getRepository(ArticleReaction::class)
            ->findOneBy(['article' => $article, 'user' => $user, 'type' => $type]);
    }

    /**
     * Реакция пользователя данного типа на комментарий.
     */
    public function findForComment(Comment $comment, User $user, ReactionType $type): ?CommentReaction
    {
        return $this->entityManager->getRepository(CommentReaction::class)
            ->findOneBy(['comment' => $comment, 'user' => $user, 'type' => $type]);
    }

    /**
     * Счётчики реакций на статью и реакции посетителя.
     */
    public function articleSummary(Article $article, ?User $viewer): ReactionSummaryData
    {
        $id = $article->persistedId();

        return $this->summaries(ArticleReaction::class, 'article', collect([$id]), $viewer)->get($id);
    }

    /**
     * Счётчики и реакции посетителя для пачки комментариев: два запроса на всю страницу.
     *
     * @param Collection<int, int> $ids идентификаторы комментариев
     *
     * @return Collection<int, ReactionSummaryData> сводки по идентификатору комментария
     */
    public function commentSummaries(Collection $ids, ?User $viewer): Collection
    {
        return $this->summaries(CommentReaction::class, 'comment', $ids, $viewer);
    }

    /**
     * Рейтинг конфликтности: записи, где много и положительных, и отрицательных реакций.
     * Мера pos·neg/(pos+neg) растёт, когда реакций много и они делятся поровну.
     *
     * @param class-string<Reaction> $class  класс реакций: на статьи или на комментарии
     * @param string                 $target поле связи в классе реакций
     *
     * @return Collection<int, ConflictScoreData>
     */
    public function conflictRating(string $class, string $target, int $limit): Collection
    {
        $positive = 'SUM(CASE WHEN r.type IN (:positive) THEN 1 ELSE 0 END)';
        $negative = 'SUM(CASE WHEN r.type IN (:negative) THEN 1 ELSE 0 END)';
        $rows = $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(r.{$target}) AS id, {$positive} AS positive, {$negative} AS negative")
            ->addSelect("({$positive} * {$negative} * 1.0) / COUNT(r.id) AS conflict")
            ->from($class, 'r')
            ->groupBy("r.{$target}")
            ->having("{$positive} > 0")
            ->andHaving("{$negative} > 0")
            ->orderBy('conflict', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('positive', self::values(ReactionType::positive()))
            ->setParameter('negative', self::values(ReactionType::negative()))
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->map(static fn (array $row): ConflictScoreData => new ConflictScoreData(
            id: (int) $row['id'],
            positive: (int) $row['positive'],
            negative: (int) $row['negative'],
            conflict: round((float) $row['conflict'], precision: 2),
        ));
    }

    /**
     * Сохраняет новую реакцию.
     */
    public function add(Reaction $reaction): void
    {
        $this->entityManager->persist($reaction);
        $this->entityManager->flush();
    }

    /**
     * Удаляет реакцию.
     */
    public function remove(Reaction $reaction): void
    {
        $this->entityManager->remove($reaction);
        $this->entityManager->flush();
    }

    /**
     * @param class-string<Reaction> $class
     * @param Collection<int, int>   $ids
     *
     * @return Collection<int, ReactionSummaryData>
     */
    private function summaries(string $class, string $target, Collection $ids, ?User $viewer): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        $counts = $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(r.{$target}) AS id, r.type, COUNT(r.id) AS total")
            ->from($class, 'r')
            ->where("r.{$target} IN (:ids)")
            ->setParameter('ids', $ids->all())
            ->groupBy("r.{$target}, r.type")
            ->getQuery()
            ->getArrayResult();
        $mine = $viewer === null ? [] : $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(r.{$target}) AS id, r.type")
            ->from($class, 'r')
            ->where("r.{$target} IN (:ids)")
            ->andWhere('r.user = :user')
            ->setParameter('ids', $ids->all())
            ->setParameter('user', $viewer)
            ->getQuery()
            ->getArrayResult();

        $zero = ReactionSummaryData::empty()->counts;
        $counts = self::grouped($counts);
        $mine = self::grouped($mine);

        return $ids->mapWithKeys(static fn (int $id): array => [$id => new ReactionSummaryData(
            counts: $zero->merge($counts->get($id, collect())
                ->pluck('total', 'type')
                ->map(static fn (mixed $total): int => (int) $total)),
            mine: $mine->get($id, collect())->pluck('type')->values(),
        )]);
    }

    /**
     * Строки результата по идентификатору записи; тип реакции приводится к строковому значению.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private static function grouped(array $rows): Collection
    {
        return collect($rows)
            ->map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'type' => $row['type']->value,
            ] + $row)
            ->groupBy('id');
    }

    /**
     * @param Collection<int, ReactionType> $types
     *
     * @return list<string>
     */
    private static function values(Collection $types): array
    {
        return $types->map(static fn (ReactionType $type): string => $type->value)->all();
    }
}
