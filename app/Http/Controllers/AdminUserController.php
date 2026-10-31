<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\User;
use App\Enums\Admin\AdminPage;
use App\Http\Requests\User\ManageUserRequest;
use App\Http\Requests\User\UpdateAdminUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Services\User\PermissionService;
use App\Services\User\UserService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Управление пользователями в админке: роли, ФИО и email, блокировка и удаление.
 * Права на пользователя проверяют запросы ManageUserRequest.
 */
final class AdminUserController extends Controller
{
    /**
     * @param ResponseFactory   $responses   ответы
     * @param Redirector        $redirector  перенаправления
     * @param UserService       $users       учётные записи
     * @param PermissionService $permissions роли
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly UserService $users,
        private readonly PermissionService $permissions,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Выдаёт или снимает роль.
     */
    public function role(User $user, UpdateUserRoleRequest $request): RedirectResponse
    {
        $request->operation()->apply($this->permissions, $user, $request->role());

        return $this->backWithStatus(__('admin.users.flash.roles_updated'));
    }

    /**
     * Сохраняет ФИО и email.
     */
    public function update(User $user, UpdateAdminUserRequest $request): RedirectResponse
    {
        $this->users->updateIdentity($user, $request->name(), $request->email());

        return $this->backToAdminPage(AdminPage::Users, __('admin.users.flash.updated'));
    }

    /**
     * Блокирует пользователя.
     */
    public function ban(User $user, ManageUserRequest $request): RedirectResponse
    {
        $this->users->ban($user);

        return $this->backWithStatus(__('admin.users.flash.banned'));
    }

    /**
     * Снимает блокировку.
     */
    public function unban(User $user, ManageUserRequest $request): RedirectResponse
    {
        $this->users->unban($user);

        return $this->backWithStatus(__('admin.users.flash.unbanned'));
    }

    /**
     * Удаляет учётную запись.
     */
    public function destroy(User $user, ManageUserRequest $request): RedirectResponse
    {
        $this->users->deleteUser($user);

        return $this->backWithStatus(__('admin.users.flash.deleted'));
    }
}
