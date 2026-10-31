<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Entities\PersonName;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Переносит users.name в поле «Имя» из ФИО.
 * Запускается между миграцией, добавляющей колонки ФИО, и миграцией, удаляющей users.name.
 */
final class ProfileNameSeeder extends Seeder
{
    /**
     * Копирует имя в first_name, пока колонка users.name ещё существует.
     */
    public function run(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        if (! $connection->createSchemaManager()->introspectTableByUnquotedName('users')->hasColumn('name')) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            UPDATE users SET first_name = SUBSTR(name, 1, ?)
            WHERE first_name IS NULL AND name IS NOT NULL AND name <> ''
            SQL, [PersonName::PART_LENGTH], [ParameterType::INTEGER]);
    }
}
