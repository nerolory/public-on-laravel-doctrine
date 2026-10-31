<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTO\User\UserData;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use App\Events\User\UserRegistered;
use App\Services\Legal\ConsentService;
use App\Services\User\UserService;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Collection;

/**
 * Регистрация: учётная запись, согласия, вход и событие UserRegistered для письма подтверждения.
 */
final class RegisterUser
{
    /**
     * @param UserService    $users    учётные записи
     * @param Hasher         $hasher   хеш пароля
     * @param StatefulGuard  $guard    вход в сессию
     * @param ConsentService $consents согласия
     * @param Dispatcher     $events   события
     * @param Application    $app      язык письма
     */
    public function __construct(
        private readonly UserService $users,
        private readonly Hasher $hasher,
        #[Auth] private readonly StatefulGuard $guard,
        private readonly ConsentService $consents,
        private readonly Dispatcher $events,
        private readonly Application $app,
    ) {
    }

    /**
     * Регистрирует и сразу впускает пользователя.
     *
     * @param Collection<int, ConsentType> $consents      согласия, отмеченные при регистрации
     * @param bool                         $emailVerified адрес уже подтверждён соцсетью: письмо не нужно
     */
    public function execute(
        UserData $data,
        string $password,
        Collection $consents,
        ?string $ip,
        ?string $userAgent,
        bool $emailVerified = false,
    ): User {
        $user = $this->users->create($data, $this->hasher->make($password));

        if ($emailVerified) {
            $this->users->verifyEmail($user);
        }

        $this->consents->accept($user, $consents, $ip, $userAgent);
        $this->guard->login($user);
        $this->events->dispatch(new UserRegistered((int) $user->getAuthIdentifier(), $this->app->getLocale()));

        return $user;
    }
}
