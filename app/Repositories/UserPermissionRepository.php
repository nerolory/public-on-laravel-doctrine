<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Role;
use App\Entities\User;
use App\Entities\UserPermission;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Роли, выданные пользователям.
 */
final class UserPermissionRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Коды выданных пользователю ролей.
     *
     * @return Collection<int, string>
     */
    public function roles(User $user): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r.code')
            ->from(UserPermission::class, 'p')
            ->join('p.role', 'r')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return collect($rows)->pluck('code');
    }

    /**
     * Коды ролей пачки пользователей одним запросом.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Collection<int, string>> роли по идентификатору пользователя
     */
    public function rolesForUsers(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        // Левое соединение от пользователей: у пользователя без назначенных ролей одна строка с code = null.
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.id, r.code')
            ->from(User::class, 'u')
            ->leftJoin(UserPermission::class, 'p', 'WITH', 'p.user = u')
            ->leftJoin('p.role', 'r')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->getQuery()
            ->getArrayResult();

        return collect($rows)
            ->groupBy(static fn (array $row): int => (int) $row['id'])
            ->map(static fn (Collection $group): Collection => $group->pluck('code')->filter()->values());
    }

    /**
     * Выдаёт роль; повторная выдача ничего не меняет.
     */
    public function grant(User $user, string $code): void
    {
        $role = $this->role($code) ?? throw new LogicException('Role not found: ' . $code);

        if ($this->permission($user, $role) !== null) {
            return;
        }

        $this->entityManager->persist(UserPermission::create($user, $role));
        $this->entityManager->flush();
    }

    /**
     * Снимает роль, если она выдана.
     */
    public function revoke(User $user, string $code): void
    {
        $role = $this->role($code);
        $permission = $role === null ? null : $this->permission($user, $role);

        if ($permission === null) {
            return;
        }

        $this->entityManager->remove($permission);
        $this->entityManager->flush();
    }

    private function role(string $code): ?Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['code' => $code]);
    }

    private function permission(User $user, Role $role): ?UserPermission
    {
        return $this->entityManager->getRepository(UserPermission::class)
            ->findOneBy(['user' => $user, 'role' => $role]);
    }
}
