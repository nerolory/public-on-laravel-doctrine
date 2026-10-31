<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ComplaintTarget;
use App\DTO\Complaint\ComplaintGroupData;
use App\Entities\Complaint;
use App\Entities\User;
use App\Enums\Complaint\ComplaintStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Жалобы: проверка повторов, открытые жалобы на объект и очередь модерации.
 * Запросы строятся по классу жалобы цели: в DQL у корня STI нет доступа к полям подклассов.
 */
final class ComplaintRepository
{
    /** Пояснений к одному объекту в очереди модерации. */
    private const int TEXTS_PER_TARGET = 3;

    /** Последних пояснений, из которых выбираются показываемые: ограничивает выборку при большой очереди. */
    private const int TEXT_SCAN_LIMIT = 200;

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Жаловался ли пользователь на объект.
     */
    public function exists(ComplaintTarget $target, object $entity, User $reporter): bool
    {
        return $this->entityManager->getRepository($target->complaintClass())
            ->count([$target->field() => $entity, 'reporter' => $reporter]) > 0;
    }

    /**
     * Число открытых жалоб на объект.
     */
    public function countOpen(ComplaintTarget $target, object $entity): int
    {
        return $this->entityManager->getRepository($target->complaintClass())
            ->count([$target->field() => $entity, 'status' => ComplaintStatus::Open]);
    }

    /**
     * Открытые жалобы на объект.
     *
     * @return Collection<int, Complaint>
     */
    public function openFor(ComplaintTarget $target, object $entity): Collection
    {
        return collect($this->entityManager->getRepository($target->complaintClass())
            ->findBy([$target->field() => $entity, 'status' => ComplaintStatus::Open]));
    }

    /**
     * Число объектов, на которые есть открытые жалобы.
     */
    public function countOpenObjects(ComplaintTarget $target): int
    {
        $field = $target->field();

        return (int) $this->entityManager->createQueryBuilder()
            ->select("COUNT(DISTINCT c.{$field})")
            ->from($target->complaintClass(), 'c')
            ->where('c.status = :open')
            ->setParameter('open', ComplaintStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Самые заметные объекты с открытыми жалобами одного типа: сначала с наибольшим числом жалоб,
     * затем с самыми ранними. Группы строятся только для первых $limit объектов.
     *
     * @return Collection<int, ComplaintGroupData>
     */
    public function openGroups(ComplaintTarget $target, int $limit): Collection
    {
        $field = $target->field();
        $objects = $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(c.{$field}) AS id, COUNT(c.id) AS total, MIN(c.createdAt) AS first")
            ->from($target->complaintClass(), 'c')
            ->where('c.status = :open')
            ->setParameter('open', ComplaintStatus::Open)
            ->groupBy("c.{$field}")
            ->orderBy('total', 'DESC')
            ->addOrderBy('first', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
        $ids = collect($objects)->map(static fn (array $row): int => (int) $row['id'])->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $reasons = $this->openReasons($target, $ids);
        $texts = $this->openTexts($target, $ids);

        return collect($objects)->map(static fn (array $row): ComplaintGroupData => new ComplaintGroupData(
            target: $target->key(),
            id: (int) $row['id'],
            total: (int) $row['total'],
            first: self::date($row['first']),
            reasons: $reasons->get((int) $row['id'], collect()),
            texts: $texts->get((int) $row['id'], collect()),
        ))->values();
    }

    /**
     * Сохраняет новую жалобу.
     */
    public function save(Complaint $complaint): void
    {
        $this->entityManager->persist($complaint);
        $this->entityManager->flush();
    }

    /**
     * Записывает изменения жалоб и объектов, по которым принято решение.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Число открытых жалоб по причинам для объектов.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Collection<string, int>> число по причине для каждого объекта
     */
    private function openReasons(ComplaintTarget $target, Collection $ids): Collection
    {
        $field = $target->field();
        $rows = $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(c.{$field}) AS id, c.reason, COUNT(c.id) AS total")
            ->from($target->complaintClass(), 'c')
            ->where('c.status = :open')
            ->andWhere("IDENTITY(c.{$field}) IN (:ids)")
            ->setParameter('open', ComplaintStatus::Open)
            ->setParameter('ids', $ids->all())
            ->groupBy("c.{$field}, c.reason")
            ->getQuery()
            ->getArrayResult();

        return collect($rows)
            ->groupBy(static fn (array $row): int => (int) $row['id'])
            ->map(static fn (Collection $reasons): Collection => $reasons->mapWithKeys(
                static fn (array $row): array => [$row['reason']->value => (int) $row['total']],
            ));
    }

    /**
     * Последние пояснения к открытым жалобам по идентификатору объекта.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Collection<int, string>>
     */
    private function openTexts(ComplaintTarget $target, Collection $ids): Collection
    {
        $field = $target->field();
        $rows = $this->entityManager->createQueryBuilder()
            ->select("IDENTITY(c.{$field}) AS id, c.text")
            ->from($target->complaintClass(), 'c')
            ->where('c.status = :open')
            ->andWhere('c.text IS NOT NULL')
            ->andWhere("IDENTITY(c.{$field}) IN (:ids)")
            ->setParameter('open', ComplaintStatus::Open)
            ->setParameter('ids', $ids->all())
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(self::TEXT_SCAN_LIMIT)
            ->getQuery()
            ->getArrayResult();

        return collect($rows)
            ->groupBy(static fn (array $row): int => (int) $row['id'])
            ->map(static fn (Collection $group): Collection => $group
                ->pluck('text')
                ->take(self::TEXTS_PER_TARGET)
                ->values());
    }

    /**
     * Doctrine возвращает агрегат даты строкой или объектом в зависимости от драйвера.
     */
    private static function date(DateTimeImmutable|string $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable($value);
    }
}
