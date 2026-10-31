<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Entities\Role;
use App\Entities\User;
use App\Repositories\UserPermissionRepository;
use Illuminate\Support\Collection;

/**
 * Роли пользователей и права, которые из них следуют.
 */
final class PermissionService
{
    /**
     * @param UserPermissionRepository $permissions выданные роли
     */
    public function __construct(
        private readonly UserPermissionRepository $permissions,
    ) {
    }

    /**
     * Коды ролей пользователя; роль USER есть у всех.
     *
     * @return Collection<int, string>
     */
    public function roles(User $user): Collection
    {
        return collect([Role::USER])->merge($this->permissions->roles($user))->unique()->values();
    }

    /**
     * Выданные роли пачки пользователей.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Collection<int, string>> роли по идентификатору пользователя
     */
    public function rolesForUsers(Collection $ids): Collection
    {
        return $this->permissions->rolesForUsers($ids);
    }

    /**
     * Открыта ли пользователю админка: модерация публикаций и жалоб.
     */
    public function canModerate(User $user): bool
    {
        return $this->hasAnyRole($user, Role::MODERATORS);
    }

    /**
     * Управляет ли пользователь другими пользователями.
     */
    public function canManageUsers(User $user): bool
    {
        return $this->hasAnyRole($user, Role::MANAGERS);
    }

    /**
     * Меняет ли пользователь настройки сайта: владелец и администратор.
     */
    public function canManageSettings(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    /**
     * Может ли $actor управлять $target и, если указана, выдавать или снимать роль $role.
     */
    public function canManage(User $actor, User $target, ?string $role = null): bool
    {
        return $this->canManageRoles(
            $actor,
            (int) $target->getAuthIdentifier(),
            $this->roles($actor),
            $this->roles($target),
            $role,
        );
    }

    /**
     * Проверка прав по уже загруженным ролям: для списков, где роли берутся одним запросом.
     * Себя менять нельзя; владелец управляет всеми, администратор — всеми, кроме владельца и администраторов.
     *
     * @param Collection<int, string> $actorRoles
     * @param Collection<int, string> $targetRoles
     */
    public function canManageRoles(
        User $actor,
        int $targetId,
        Collection $actorRoles,
        Collection $targetRoles,
        ?string $role = null,
    ): bool {
        $isSelf = (int) $actor->getAuthIdentifier() === $targetId;

        if ($actor->isBanned() || $isSelf || $actorRoles->intersect(Role::MANAGERS)->isEmpty()) {
            return false;
        }

        if ($actorRoles->contains(Role::OWNER)) {
            return true;
        }

        return $targetRoles->intersect(Role::MANAGERS)->isEmpty()
            && ! in_array($role, Role::MANAGERS, strict: true);
    }

    /**
     * Роли, которые $actor может выдавать и снимать.
     *
     * @return Collection<int, string>
     */
    public function assignableRoles(User $actor): Collection
    {
        $owner = $this->roles($actor)->contains(Role::OWNER);

        return collect(Role::CODES)
            ->reject(static fn (string $role): bool => $role === Role::USER
                || (! $owner && in_array($role, Role::MANAGERS, strict: true)))
            ->values();
    }

    /**
     * Выдаёт роль; право на это проверяет UserPolicy.
     */
    public function grant(User $target, string $role): void
    {
        $this->permissions->grant($target, $role);
    }

    /**
     * Снимает роль; право на это проверяет UserPolicy.
     */
    public function revoke(User $target, string $role): void
    {
        $this->permissions->revoke($target, $role);
    }

    /**
     * Есть ли у незаблокированного пользователя хотя бы одна из ролей.
     *
     * @param list<string> $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        return ! $user->isBanned() && $this->roles($user)->intersect($roles)->isNotEmpty();
    }
}
