<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\AccountSelfDeletion;
use App\Entities\User;
use App\Services\User\PermissionService;
use Illuminate\Auth\Access\Response;

/**
 * Управление учётными записями: администратором и самим пользователем.
 */
final class UserPolicy
{
    /**
     * @param PermissionService   $permissions роли пользователя
     * @param AccountSelfDeletion $deletion    удаление своей учётной записи
     */
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AccountSelfDeletion $deletion,
    ) {
    }

    /**
     * Публичная карточка автора; заблокированного автора для посетителей нет.
     */
    public function viewProfile(?User $viewer, User $author): Response
    {
        return $author->isBanned() ? Response::denyAsNotFound() : Response::allow();
    }

    /**
     * Правка, блокировка и удаление пользователя администратором.
     */
    public function manage(User $actor, User $target): bool
    {
        return $this->permissions->canManage($actor, $target);
    }

    /**
     * Выдача и снятие роли: администратор не назначает роли управления.
     */
    public function assignRole(User $actor, User $target, string $role): bool
    {
        return $this->permissions->canManage($actor, $target, $role);
    }

    /**
     * Удаление своей учётной записи; владелец сайта себя удалить не может.
     */
    public function delete(User $actor, User $target): Response
    {
        if (! $actor->is($target)) {
            return Response::deny();
        }

        return $this->deletion->canDeleteOwnAccount($actor)
            ? Response::allow()
            : Response::deny(__('messages.account.owner_cannot_delete'));
    }
}
