<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entities\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Фильтр Doctrine: удалённые пользователи не попадают в выборки.
 */
final class ActiveUserFilter extends SQLFilter
{
    /**
     * Условие deleted_at IS NULL для таблицы пользователей.
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getName() !== User::class) {
            return '';
        }

        return $targetTableAlias . '.deleted_at IS NULL';
    }
}
