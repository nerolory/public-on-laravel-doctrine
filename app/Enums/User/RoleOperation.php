<?php

declare(strict_types=1);

namespace App\Enums\User;

use App\Entities\User;
use App\Services\User\PermissionService;

/**
 * Операция администратора над ролью пользователя: выдача или снятие.
 */
enum RoleOperation: string
{
    /** Выдать роль. */
    case Grant = 'grant';

    /** Снять роль. */
    case Revoke = 'revoke';

    /**
     * Выдаёт или снимает роль у пользователя.
     */
    public function apply(PermissionService $permissions, User $target, string $role): void
    {
        match ($this) {
            self::Grant => $permissions->grant($target, $role),
            self::Revoke => $permissions->revoke($target, $role),
        };
    }
}
