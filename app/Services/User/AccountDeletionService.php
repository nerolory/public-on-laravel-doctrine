<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\AccountSelfDeletion;
use App\Entities\Role;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use App\Events\User\AccountDeleted;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;

/**
 * Удаление пользователем своей учётной записи. Владелец сайта себя удалить не может:
 * сначала роль владельца нужно передать другому пользователю.
 */
final class AccountDeletionService implements AccountSelfDeletion
{
    /**
     * @param PermissionService $permissions роли пользователя
     * @param UserService       $users       учётные записи
     * @param Dispatcher        $events      события приложения
     */
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly UserService $users,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * Может ли пользователь удалить свою учётную запись: все, кроме владельца сайта.
     */
    public function canDeleteOwnAccount(User $user): bool
    {
        return ! $this->permissions->roles($user)->contains(Role::OWNER);
    }

    /**
     * Удаляет учётную запись; отзыв согласий записывает слушатель события AccountDeleted.
     *
     * @param Collection<int, ConsentType> $revokedConsents
     */
    public function deleteOwnAccount(User $user, Collection $revokedConsents, ?string $ip, ?string $userAgent): void
    {
        $this->users->deleteUser($user);
        $this->events->dispatch(new AccountDeleted($user, $revokedConsents, $ip, $userAgent));
    }
}
