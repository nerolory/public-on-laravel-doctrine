<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Actions\RegisterUser;
use App\Contracts\UserRepository;
use App\DTO\Social\SocialIdentityData;
use App\DTO\User\UserData;
use App\Entities\SocialAccount;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use App\Enums\Social\SocialLoginResult;
use App\Enums\Social\SocialProvider;
use App\Repositories\SocialAccountRepository;
use App\Services\User\UserService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\InvalidStateException;
use LogicException;
use Psr\Log\LoggerInterface;

/**
 * Вход через соцсеть: известная привязка — вход; совпал email — привязка к существующей учётной записи;
 * иначе регистрация ждёт принятия согласий (и ввода email, если соцсеть его не дала).
 */
final class SocialAuthService
{
    /** Длина случайного пароля: пользователь соцсети входит без пароля, пока не задаст свой. */
    private const int RANDOM_PASSWORD_LENGTH = 40;

    /** Ключ сессии с данными соцсети, пока регистрация ждёт согласий. */
    private const string PENDING_SESSION_KEY = 'social.pending';

    /**
     * @param SocialAccountRepository $accounts     привязки соцсетей
     * @param UserRepository          $users        пользователи
     * @param UserService             $userAccounts подтверждение адреса почты
     * @param RegisterUser            $register     регистрация
     * @param StatefulGuard           $guard        вход в сессию
     * @param Session                 $session      данные соцсети до завершения регистрации
     * @param SocialProviders         $providers    настройка провайдера перед переходом на его страницу
     * @param Socialite               $socialite    OAuth-драйверы Socialite
     * @param LoggerInterface         $logger       журнал ошибок входа
     */
    public function __construct(
        private readonly SocialAccountRepository $accounts,
        private readonly UserRepository $users,
        private readonly UserService $userAccounts,
        private readonly RegisterUser $register,
        #[Auth] private readonly StatefulGuard $guard,
        private readonly Session $session,
        private readonly SocialProviders $providers,
        private readonly Socialite $socialite,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Адрес страницы входа провайдера; параметры провайдера настраиваются до перехода на него.
     */
    public function authorizationUrl(SocialProvider $provider): string
    {
        $this->providers->configure($provider);

        return $this->socialite->driver($provider->value)->redirect()->getTargetUrl();
    }

    /**
     * Вход по ответу провайдера; сбой обмена с провайдером записывается в журнал и даёт результат Failed.
     */
    public function loginFromCallback(SocialProvider $provider): SocialLoginResult
    {
        $this->providers->configure($provider);

        try {
            $user = $this->socialite->driver($provider->value)->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            $this->logger->warning(
                'Вход через соцсеть не выполнен',
                ['provider' => $provider->value, 'exception' => $exception],
            );

            return SocialLoginResult::Failed;
        }

        return $this->handle($this->identityOf($provider, $user));
    }

    /**
     * Входит знакомым пользователем, сообщает, что нужна регистрация, или отказывает;
     * для регистрации данные соцсети сохраняются в сессии.
     */
    public function handle(SocialIdentityData $identity): SocialLoginResult
    {
        $user = $this->accounts->find($identity->provider, $identity->id)?->getUser()
            ?? ($identity->email === null ? null : $this->users->findByEmail($identity->email));

        if ($user === null) {
            $this->holdPending($identity);

            return SocialLoginResult::Pending;
        }

        if ($user->isBanned() || $user->isDeleted()) {
            return SocialLoginResult::Failed;
        }

        $this->link($user, $identity);

        if (self::confirmsEmail($identity, $user->getEmail())) {
            $this->userAccounts->verifyEmail($user);
        }

        $this->guard->login($user, remember: true);

        return SocialLoginResult::LoggedIn;
    }

    /**
     * Ждут ли в сессии данные соцсети.
     */
    public function hasPending(): bool
    {
        return $this->session->has(self::PENDING_SESSION_KEY);
    }

    /**
     * Данные соцсети из сессии; сами данные остаются в сессии.
     *
     * @throws LogicException в сессии нет данных соцсети
     */
    public function pendingIdentity(): SocialIdentityData
    {
        return self::fromSession($this->session->get(self::PENDING_SESSION_KEY));
    }

    /**
     * Забирает данные соцсети из сессии: после завершения регистрации они не нужны.
     *
     * @throws LogicException в сессии нет данных соцсети
     */
    public function pullPending(): SocialIdentityData
    {
        return self::fromSession($this->session->pull(self::PENDING_SESSION_KEY));
    }

    /**
     * Завершает регистрацию через соцсеть после принятия согласий.
     *
     * @param Collection<int, ConsentType> $consents
     */
    public function complete(
        SocialIdentityData $identity,
        string $email,
        Collection $consents,
        ?string $ip,
        ?string $userAgent,
    ): User {
        $user = $this->register->execute(
            data: new UserData($identity->name, $email),
            password: Str::random(self::RANDOM_PASSWORD_LENGTH),
            consents: $consents,
            ip: $ip,
            userAgent: $userAgent,
            emailVerified: self::confirmsEmail($identity, $email),
        );
        $this->link($user, $identity);

        return $user;
    }

    /**
     * Данные соцсети из ответа OAuth-провайдера; пустые имя и адрес почты — null.
     */
    private function identityOf(SocialProvider $provider, SocialiteUser $user): SocialIdentityData
    {
        return new SocialIdentityData(
            provider: $provider->value,
            id: (string) $user->getId(),
            email: self::nullIfBlank($user->getEmail()),
            name: self::nullIfBlank($user->getName()),
        );
    }

    /**
     * Сохраняет данные соцсети в сессии до принятия согласий.
     */
    private function holdPending(SocialIdentityData $identity): void
    {
        $this->session->put(self::PENDING_SESSION_KEY, $identity->toArray());
    }

    /**
     * Привязывает соцсеть к учётной записи, если привязки ещё нет.
     */
    private function link(User $user, SocialIdentityData $identity): void
    {
        if ($this->accounts->find($identity->provider, $identity->id) === null) {
            $this->accounts->save(SocialAccount::link($user, $identity->provider, $identity->id));
        }
    }

    /**
     * Восстанавливает данные соцсети из значения сессии.
     *
     * @throws LogicException в сессии нет данных соцсети
     */
    private static function fromSession(mixed $data): SocialIdentityData
    {
        if (! is_array($data)) {
            throw new LogicException('No social identity is pending in the session.');
        }

        return SocialIdentityData::fromArray($data);
    }

    /**
     * Пустая строка из соцсети означает отсутствие значения.
     */
    private static function nullIfBlank(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * Совпадает ли адрес из соцсети с указанным: такой адрес подтверждён провайдером.
     */
    private static function confirmsEmail(SocialIdentityData $identity, string $email): bool
    {
        return $identity->email !== null && mb_strtolower($identity->email) === mb_strtolower($email);
    }
}
