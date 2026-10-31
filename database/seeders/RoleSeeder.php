<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Entities\Role;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Роли доступа из Role::CODES. Существующие роли не меняются.
 */
final class RoleSeeder extends Seeder
{
    /**
     * Создаёт роли, которых ещё нет в базе.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $roles = $entityManager->getRepository(Role::class);
        foreach (Role::CODES as $code) {
            if ($roles->findOneBy(['code' => $code]) === null) {
                $entityManager->persist(Role::create($code));
            }
        }
        $entityManager->flush();
    }
}
