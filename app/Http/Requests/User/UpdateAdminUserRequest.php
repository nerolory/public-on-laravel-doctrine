<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Entities\PersonName;
use App\Services\User\UserService;
use App\Traits\ValidatesAccountFields;

/**
 * Правка ФИО и email пользователя администратором.
 */
final class UpdateAdminUserRequest extends ManageUserRequest
{
    use ValidatesAccountFields;

    /**
     * Правила проверки ФИО и email.
     *
     * @return array<string, mixed>
     */
    public function rules(UserService $users): array
    {
        return [
            'last_name' => $this->personNameRules(),
            'first_name' => $this->personNameRules(),
            'middle_name' => $this->personNameRules(),
            'email' => $this->emailRules($users, $this->target()->persistedId()),
        ];
    }

    /**
     * Новые фамилия, имя и отчество.
     */
    public function name(): PersonName
    {
        return new PersonName(
            $this->validated('last_name'),
            $this->validated('first_name'),
            $this->validated('middle_name'),
        );
    }

    /**
     * Новый адрес почты.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
