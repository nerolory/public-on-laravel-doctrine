<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\User;

use App\DTO\Admin\AdminRoleToggleData;
use App\DTO\Admin\AdminUserRowData;
use App\DTO\User\UserRowData;
use App\Entities\Role;
use App\Enums\User\Ability;
use App\Enums\User\RoleOperation;
use App\Services\User\PermissionService;
use App\Services\User\UserService;
use App\Support\Settings\PageSizes;
use App\Traits\AuthorizesAdminPage;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Список пользователей: роли, блокировка и правка для тех, кем администратор вправе управлять.
 */
final class UserIndexPage extends Page
{
    use AuthorizesAdminPage;
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/users. */
    protected ?string $alias = 'users';

    /**
     * @param CoreContract      $core        ядро MoonShine
     * @param UserService       $users       учётные записи
     * @param PageSizes         $pageSizes   размер страницы списка
     * @param PermissionService $permissions роли
     * @param AuthFactory       $auth        текущий администратор
     * @param Gate              $gate        права доступа
     * @param ViewFactory       $views       шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly UserService $users,
        private readonly PageSizes $pageSizes,
        private readonly PermissionService $permissions,
        private readonly AuthFactory $auth,
        private readonly Gate $gate,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.users.title');
    }

    /**
     * Хлебные крошки: админка → пользователи.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление пользователями.
     */
    protected function ability(): Ability
    {
        return Ability::ManageUsers;
    }

    /**
     * Таблица пользователей и постраничная навигация.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $actor = $this->auth->guard()->user();
        $page = $this->pageNumber();
        $size = $this->pageSizes->admin();
        $users = $this->users->page($page, $size);
        $userIds = $users->items->map(static fn (UserRowData $user): int => $user->id);
        $extraRoles = $this->permissions->rolesForUsers($userIds);
        $actorRoles = $this->permissions->roles($actor);
        $assignable = $this->permissions->assignableRoles($actor);

        $rows = $users->items->map(
            function (UserRowData $user) use ($actor, $actorRoles, $extraRoles, $assignable): AdminUserRowData {
                $roles = $extraRoles->get($user->id);
                $manageable = $this->permissions->canManageRoles($actor, $user->id, $actorRoles, $roles);

                return $this->row($user, $roles, $manageable, $assignable);
            },
        );

        return $this->listComponents($this->views->make('admin.users', ['users' => $rows]), $users, $page, $size);
    }

    /**
     * Строка таблицы: роли, блокировка и действия, доступные администратору.
     *
     * @param Collection<int, string> $extraRoles роли пользователя сверх роли по умолчанию
     * @param Collection<int, string> $assignable роли, которые администратор вправе выдавать
     */
    private function row(
        UserRowData $user,
        Collection $extraRoles,
        bool $manageable,
        Collection $assignable,
    ): AdminUserRowData {
        $banned = $user->bannedAt !== null;
        $rolesLabel = $extraRoles
            ->reject(static fn (string $role): bool => $role === Role::USER)
            ->map(static fn (string $role): string => __('roles.' . $role))
            ->prepend(__('roles.' . Role::USER))
            ->implode(', ');

        return new AdminUserRowData(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            banned: $banned,
            manageable: $manageable,
            rolesLabel: $rolesLabel,
            editUrl: $this->adminPageUrl(UserFormPage::class, ['id' => $user->id]),
            banRoute: $banned ? 'admin.users.unban' : 'admin.users.ban',
            banIcon: $banned ? 'lock-open' : 'lock-closed',
            banTitle: __($banned ? 'admin.users.unban' : 'admin.users.ban'),
            banAriaLabel: __($banned ? 'admin.users.unban_named' : 'admin.users.ban_named', ['name' => $user->name]),
            roleToggles: $manageable
                ? $assignable->map(fn (string $code): AdminRoleToggleData => $this->toggle($code, $extraRoles))
                : collect(),
        );
    }

    /**
     * Переключатель роли: выдача, если роли нет, и снятие, если есть.
     *
     * @param Collection<int, string> $extraRoles роли пользователя
     */
    private function toggle(string $code, Collection $extraRoles): AdminRoleToggleData
    {
        $checked = $extraRoles->contains($code);

        return new AdminRoleToggleData(
            code: $code,
            label: __('roles.' . $code),
            checked: $checked,
            operation: ($checked ? RoleOperation::Revoke : RoleOperation::Grant)->value,
            submitLabel: __($checked ? 'admin.users.revoke' : 'admin.users.grant'),
        );
    }
}
