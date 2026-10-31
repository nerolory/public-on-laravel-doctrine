<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTO\User\UserData;
use App\Services\Legal\ConsentService;
use App\Services\User\UserService;
use App\Traits\AcceptsConsents;
use App\Traits\ValidatesAccountFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Регистрация: состав обязательных согласий задаёт политика региона из настроек сайта.
 */
final class RegisterRequest extends FormRequest
{
    use AcceptsConsents;
    use ValidatesAccountFields;

    /** Наименьшая длина пароля: совпадает с лимитом формы, который получает фронтенд из /api/site. */
    public const int PASSWORD_MIN_LENGTH = 8;

    /**
     * Правила проверки имени, email, пароля и согласий.
     *
     * @return array<string, mixed>
     */
    public function rules(UserService $users, ConsentService $consents): array
    {
        return [
            'name' => $this->personNameRules(required: true),
            'email' => $this->emailRules($users),
            'password' => ['required', 'string', 'min:' . self::PASSWORD_MIN_LENGTH, 'confirmed'],
            ...$this->consentRules($consents),
        ];
    }

    /**
     * Имя и email нового пользователя.
     */
    public function toData(): UserData
    {
        return new UserData((string) $this->validated('name'), (string) $this->validated('email'));
    }

    /**
     * Пароль нового пользователя.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
