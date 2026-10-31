<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Entities\Role;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Переносит флаг администратора из user_permissions.is_admin в роли. Запускается между миграцией,
 * добавляющей роли, и миграцией, удаляющей флаг.
 */
final class LegacyPermissionSeeder extends Seeder
{
    /**
     * Проставляет роли записям без роли: администраторам — роль администратора, остальным — роль пользователя.
     */
    public function run(EntityManagerInterface $entityManager, RoleSeeder $roleSeeder): void
    {
        $roleSeeder->run($entityManager);
        $connection = $entityManager->getConnection();
        foreach ([Role::USER => false, Role::ADMINISTRATOR => true] as $code => $isAdmin) {
            $role = $entityManager->getRepository(Role::class)->findOneBy(['code' => $code]);
            $roleId = $entityManager->getClassMetadata(Role::class)->getIdentifierValues($role)['id'];
            $connection->createQueryBuilder()->update('user_permissions')
                ->set('role_id', ':role')->where('is_admin = :admin')->andWhere('role_id IS NULL')
                ->setParameter('role', $roleId)->setParameter('admin', $isAdmin, ParameterType::BOOLEAN)
                ->executeStatement();
        }
    }
}
