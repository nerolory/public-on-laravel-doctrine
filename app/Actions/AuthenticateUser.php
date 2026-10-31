<?php

declare(strict_types=1);

namespace App\Actions;

use App\Entities\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Вход по email и паролю; заблокированный пользователь не входит.
 */
final class AuthenticateUser
{
    /**
     * @param StatefulGuard $guard вход в сессию
     */
    public function __construct(
        #[Auth] private readonly StatefulGuard $guard,
    ) {
    }

    /**
     * Впускает пользователя; false — неверные данные или блокировка.
     */
    public function execute(string $email, string $password): bool
    {
        if (! $this->guard->attempt(['email' => $email, 'password' => $password])) {
            return false;
        }

        $user = $this->guard->user();

        if ($user instanceof User && $user->isBanned()) {
            $this->guard->logout();

            return false;
        }

        return true;
    }
}
