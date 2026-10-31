<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\User\UserService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Адрес почты не занят другим пользователем, в том числе удалённым.
 */
final readonly class UniqueEmail implements ValidationRule
{
    /**
     * @param UserService $users    пользователи
     * @param int|null    $exceptId пользователь, чей адрес не считается занятым
     */
    public function __construct(
        private UserService $users,
        private ?int $exceptId = null,
    ) {
    }

    /**
     * Сообщает об ошибке, если адрес занят.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->users->emailInUse((string) $value, $this->exceptId)) {
            $fail(__('messages.auth.email_taken'));
        }
    }
}
