<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\User;

/**
 * Цель жалобы, у которой объект принадлежит пользователю: автор статьи, альбома или комментария.
 */
trait ChecksOwnership
{
    /**
     * Принадлежит ли объект пользователю: владельца сущности определяет её метод isOwnedBy().
     */
    public function isOwnedBy(object $entity, User $user): bool
    {
        return $entity->isOwnedBy($user);
    }
}
