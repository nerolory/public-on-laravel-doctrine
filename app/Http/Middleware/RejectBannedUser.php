<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\LogoutUser;
use App\Entities\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Завершает сессию пользователя, которого заблокировали, пока он был на сайте.
 */
final class RejectBannedUser
{
    /**
     * @param LogoutUser $logout выход
     */
    public function __construct(
        private readonly LogoutUser $logout,
    ) {
    }

    /**
     * Ответ 403 и выход для заблокированного пользователя.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isBanned()) {
            $this->logout->execute();
            throw new AuthorizationException(__('messages.auth.banned'));
        }

        return $next($request);
    }
}
