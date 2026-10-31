<?php

declare(strict_types=1);

namespace App\Policies;

use App\Entities\Album;
use App\Entities\User;
use App\Services\User\PermissionService;
use App\Traits\AllowsOwnerOrModerator;
use Illuminate\Auth\Access\Response;

/**
 * Доступ к альбому: просмотр и работа владельца.
 */
final class AlbumPolicy
{
    use AllowsOwnerOrModerator;

    /**
     * @param PermissionService $permissions роли пользователя
     */
    public function __construct(
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * Опубликованный альбом видят все, остальные — владелец и модераторы.
     */
    public function view(?User $user, Album $album): Response
    {
        if ($album->isPublished() || ($user !== null && $this->isOwnerOrModerator($user, $album, $this->permissions))) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /**
     * Правит и удаляет альбом только владелец; чужой альбом для него не существует.
     */
    public function update(User $user, Album $album): Response
    {
        return $album->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
