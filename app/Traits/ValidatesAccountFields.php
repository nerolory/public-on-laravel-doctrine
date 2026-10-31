<?php

declare(strict_types=1);

namespace App\Traits;

use App\Entities\PersonName;
use App\Entities\User;
use App\Rules\UniqueEmail;
use App\Services\User\UserService;

/**
 * Общие правила полей аккаунта: адрес почты и части ФИО.
 */
trait ValidatesAccountFields
{
    /**
     * Правила адреса почты: обязательный, допустимый и не занятый другим пользователем.
     *
     * @return list<string|object>
     */
    protected function emailRules(UserService $users, ?int $exceptUserId = null): array
    {
        return [
            'bail',
            'required',
            'string',
            'email',
            'max:' . User::EMAIL_LENGTH,
            new UniqueEmail($users, $exceptUserId),
        ];
    }

    /**
     * Правила части ФИО: фамилии, имени, отчества или имени целиком.
     *
     * @return list<string>
     */
    protected function personNameRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:' . PersonName::PART_LENGTH];
    }
}
