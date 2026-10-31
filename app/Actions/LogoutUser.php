<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;

/**
 * Выход: сессия сбрасывается, CSRF-токен выдаётся заново.
 */
final class LogoutUser
{
    /**
     * @param StatefulGuard $guard   вход в сессию
     * @param Session       $session сессия текущего запроса
     */
    public function __construct(
        #[Auth] private readonly StatefulGuard $guard,
        private readonly Session $session,
    ) {
    }

    /**
     * Завершает сессию пользователя.
     */
    public function execute(): void
    {
        $this->guard->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}
