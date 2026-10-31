<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Legal\ConsentRecordData;
use App\Entities\Consent;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Журнал согласий пользователей.
 */
final class ConsentRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Последняя запись журнала по каждому виду согласия.
     *
     * @return Collection<string, Consent> по значению ConsentType
     */
    public function latestByType(User $user): Collection
    {
        $consents = $this->entityManager->createQueryBuilder()
            ->select('c', 'd')
            ->from(Consent::class, 'c')
            ->join('c.document', 'd')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Записи идут по времени: более поздняя запись того же вида заменяет раннюю.
        return collect($consents)
            ->keyBy(static fn (Consent $consent): string => $consent->getDocument()->getType()->value);
    }

    /**
     * Полная история согласий для выгрузки данных.
     *
     * @return Collection<int, ConsentRecordData>
     */
    public function history(User $user): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('d.type, d.version, d.title, c.agreed, c.createdAt')
            ->from(Consent::class, 'c')
            ->join('c.document', 'd')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->map(static fn (array $row): ConsentRecordData => new ConsentRecordData(
            type: $row['type']->value,
            version: (int) $row['version'],
            title: $row['title'],
            agreed: (bool) $row['agreed'],
            createdAt: $row['createdAt'],
        ));
    }

    /**
     * Добавляет записи в журнал одной транзакцией.
     *
     * @param Collection<int, Consent> $consents
     */
    public function add(Collection $consents): void
    {
        $consents->each(fn (Consent $consent) => $this->entityManager->persist($consent));
        $this->entityManager->flush();
    }
}
