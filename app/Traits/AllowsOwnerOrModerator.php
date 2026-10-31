<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\Album;
use App\Entities\Article;
use App\Entities\Chart;
use App\Entities\Comment;
use App\Entities\User;
use App\Services\User\PermissionService;

/**
 * Доступ к записи автору и модераторам: автор работает со своей записью, модератор — с любой.
 */
trait AllowsOwnerOrModerator
{
    /**
     * Автор записи или модератор.
     *
     * @param Article|Album|Chart|Comment $entity      запись с владельцем
     * @param PermissionService           $permissions роли пользователя
     */
    private function isOwnerOrModerator(
        User $user,
        Article|Album|Chart|Comment $entity,
        PermissionService $permissions,
    ): bool {
        return $entity->isOwnedBy($user) || $permissions->canModerate($user);
    }
}
