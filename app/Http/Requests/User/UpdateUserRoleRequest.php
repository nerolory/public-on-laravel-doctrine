<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Entities\Role;
use App\Enums\User\RoleOperation;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Validation\Rule;

/**
 * Выдача или снятие роли пользователю.
 */
final class UpdateUserRoleRequest extends ManageUserRequest
{
    /**
     * Роль должна быть в числе тех, которыми администратор управляет у этого пользователя.
     */
    public function authorize(Gate $gate): Response
    {
        return $gate->inspect('assignRole', [$this->target(), (string) $this->input('role')]);
    }

    /**
     * Правила проверки роли и операции.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(Role::CODES), Rule::notIn([Role::USER])],
            'operation' => ['required', Rule::enum(RoleOperation::class)],
        ];
    }

    /**
     * Код роли.
     */
    public function role(): string
    {
        return (string) $this->validated('role');
    }

    /**
     * Выдача или снятие роли.
     */
    public function operation(): RoleOperation
    {
        return RoleOperation::from((string) $this->validated('operation'));
    }
}
