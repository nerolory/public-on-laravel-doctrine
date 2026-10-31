<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Common\PageData;
use App\DTO\Poll\PollResultsData;
use App\Entities\Article;
use App\Entities\Poll;
use App\Entities\PollBallot;
use App\Entities\PollOption;
use App\Entities\User;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Опросы статей, бюллетени и итоги голосования.
 */
final class PollRepository
{
    /** Последние проголосовавшие по каждому варианту: один запрос с оконной функцией на весь опрос. */
    private const string RECENT_VOTERS_SQL = 'SELECT option_id, user_id FROM ('
        . 'SELECT bo.option_id, b.user_id, '
        . 'ROW_NUMBER() OVER (PARTITION BY bo.option_id ORDER BY b.updated_at DESC, b.id DESC) AS place '
        . 'FROM poll_ballot_options bo '
        . 'JOIN poll_ballots b ON b.id = bo.ballot_id '
        . 'JOIN users u ON u.id = b.user_id AND u.deleted_at IS NULL '
        . 'WHERE b.poll_id = ?'
        . ') ranked WHERE place <= ? ORDER BY option_id, place';

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Опрос статьи с вариантами.
     */
    public function findByArticle(Article $article): ?Poll
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p, o')
            ->from(Poll::class, 'p')
            ->leftJoin('p.options', 'o')
            ->where('p.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Бюллетень пользователя в опросе.
     */
    public function ballotOf(Poll $poll, User $user): ?PollBallot
    {
        return $this->entityManager->getRepository(PollBallot::class)->findOneBy(['poll' => $poll, 'user' => $user]);
    }

    /**
     * Голосовал ли кто-нибудь в опросе.
     */
    public function hasBallots(Poll $poll): bool
    {
        return (bool) $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(PollBallot::class, 'b')
            ->where('b.poll = :poll')
            ->setParameter('poll', $poll)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Голоса по вариантам и число проголосовавших. Бюллетени удалённых пользователей не учитываются:
     * присоединение пользователя включает SQL-фильтр активных учётных записей.
     */
    public function results(Poll $poll): PollResultsData
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('o.id AS option, COUNT(b.id) AS votes')
            ->from(PollBallot::class, 'b')
            ->join('b.user', 'u')
            ->join('b.options', 'o')
            ->where('b.poll = :poll')
            ->setParameter('poll', $poll)
            ->groupBy('o.id')
            ->getQuery()
            ->getArrayResult();
        $voters = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(PollBallot::class, 'b')
            ->join('b.user', 'u')
            ->where('b.poll = :poll')
            ->setParameter('poll', $poll)
            ->getQuery()
            ->getSingleScalarResult();
        $votes = collect($rows)->mapWithKeys(static fn (array $row): array => [
            (int) $row['option'] => (int) $row['votes'],
        ]);

        return new PollResultsData($votes, $voters);
    }

    /**
     * Последние проголосовавшие по каждому варианту.
     *
     * @return Collection<int, Collection<int, int>> идентификаторы пользователей по идентификатору варианта
     */
    public function recentVoters(Poll $poll, int $perOption): Collection
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::RECENT_VOTERS_SQL,
            [$poll->getId(), $perOption],
            // Явные целые типы: без них SQLite сравнивает число с текстом и лимит по местам не применяется.
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return collect($rows)
            ->groupBy(static fn (array $row): int => (int) $row['option_id'])
            ->map(static fn (Collection $group): Collection => $group
                ->map(static fn (array $row): int => (int) $row['user_id'])
                ->values());
    }

    /**
     * Проголосовавшие за вариант: недавние первыми.
     *
     * @return PageData<User>
     */
    public function voters(PollOption $option, int $page, int $limit): PageData
    {
        $users = $this->entityManager->createQueryBuilder()
            ->select('u, av')
            ->from(User::class, 'u')
            ->leftJoin('u.avatar', 'av')
            ->join(PollBallot::class, 'b', 'WITH', 'b.user = u')
            ->join('b.options', 'o')
            ->where('o = :option')
            ->setParameter('option', $option)
            ->orderBy('b.updatedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            // Лишняя запись показывает, есть ли следующая страница.
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return PageData::fromOverfetched(collect($users), $limit);
    }

    /**
     * Пользователи с аватарами по идентификаторам.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, User> пользователи по идентификатору
     */
    public function usersByIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $users = $this->entityManager->createQueryBuilder()
            ->select('u, av')
            ->from(User::class, 'u')
            ->leftJoin('u.avatar', 'av')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids->unique()->values()->all())
            ->getQuery()
            ->getResult();

        return collect($users)->keyBy(static fn (User $user): int => $user->persistedId());
    }

    /**
     * Удаляет опрос с бюллетенями в одной транзакции: сначала варианты бюллетеней, затем бюллетени, затем опрос.
     */
    public function delete(Poll $poll): void
    {
        $this->entityManager->wrapInTransaction(function () use ($poll): void {
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement(
                'DELETE FROM poll_ballot_options WHERE ballot_id IN (SELECT id FROM poll_ballots WHERE poll_id = ?)',
                [$poll->getId()],
            );
            $connection->executeStatement('DELETE FROM poll_ballots WHERE poll_id = ?', [$poll->getId()]);
            $this->entityManager->remove($poll);
            $this->entityManager->flush();
        });
    }

    /**
     * Сохраняет опрос или бюллетень.
     */
    public function save(Poll|PollBallot $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Удаляет бюллетень.
     */
    public function remove(PollBallot $ballot): void
    {
        $this->entityManager->remove($ballot);
        $this->entityManager->flush();
    }
}
