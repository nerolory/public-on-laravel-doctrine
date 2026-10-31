<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\UserRepository as UserRepositoryContract;
use App\DTO\Common\PageData;
use App\DTO\User\UserCountsData;
use App\DTO\User\UserRowData;
use App\Entities\PersonName;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Пользователи в Doctrine.
 */
final class UserRepository implements UserRepositoryContract
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Пользователь по идентификатору; удалённые не находятся.
     */
    public function find(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    /**
     * Пользователь по адресу почты.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Занят ли адрес почты. Запрос идёт мимо фильтра Doctrine: адрес удалённого пользователя тоже занят.
     */
    public function emailInUse(string $email, ?int $exceptId = null): bool
    {
        $query = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('users')
            ->where('email = :email')
            ->setParameter('email', $email);

        if ($exceptId !== null) {
            $query->andWhere('id <> :id')->setParameter('id', $exceptId);
        }

        return $query->fetchOne() !== false;
    }

    /**
     * Сохраняет пользователя.
     */
    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Страница списка пользователей для админки, новые первыми.
     *
     * @return PageData<UserRowData>
     */
    public function page(int $page, int $limit): PageData
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.id, u.email, u.bannedAt')
            ->addSelect('u.fullName.firstName AS firstName, u.fullName.middleName AS middleName')
            ->addSelect('u.fullName.lastName AS lastName')
            ->from(User::class, 'u')
            ->orderBy('u.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getArrayResult();
        $items = collect($rows)->map(static fn (array $row): UserRowData => new UserRowData(
            id: (int) $row['id'],
            name: PersonName::displayOrEmail($row['firstName'], $row['middleName'], $row['lastName'], $row['email']),
            email: $row['email'],
            bannedAt: $row['bannedAt'],
        ));

        return PageData::fromOverfetched($items, $limit, total: $this->counts()->total);
    }

    /**
     * Имя и email пользователей по идентификатору для списков в админке.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, string>
     */
    public function labels(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return collect($this->entityManager->getRepository(User::class)->findBy(['id' => $ids->all()]))
            ->mapWithKeys(static fn (User $user): array => [
                $user->persistedId() => $user->getName() . ' (' . $user->getEmail() . ')',
            ]);
    }

    /**
     * Число пользователей без удалённых и заблокированных среди них.
     */
    public function counts(): UserCountsData
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id) AS total, SUM(CASE WHEN u.bannedAt IS NOT NULL THEN 1 ELSE 0 END) AS banned')
            ->from(User::class, 'u')
            ->where('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleResult();

        return new UserCountsData(total: (int) $row['total'], banned: (int) $row['banned']);
    }
}
