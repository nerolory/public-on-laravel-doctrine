<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Вход по email и паролю: проверяет только поля, вход выполняет контроллер.
 */
final class LoginRequest extends FormRequest
{
    /**
     * Правила проверки email и пароля.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Адрес почты.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * Пароль.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
