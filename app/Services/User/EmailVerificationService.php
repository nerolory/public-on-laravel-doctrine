<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\UserRepository;
use App\Entities\User;
use App\Mail\VerifyEmailMail;
use App\Services\Settings\SettingsService;
use App\Support\Seo\SiteUrls;
use DateTimeImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Подтверждение email подписанной ссылкой со сроком действия.
 */
final class EmailVerificationService
{
    /** Длина отпечатка адреса в ссылке: sha1 в шестнадцатеричном виде. */
    public const int HASH_LENGTH = 40;

    /** Ссылка действует сутки: письмо успевают открыть, даже если оно пришло ночью. */
    private const int LINK_LIFETIME_MINUTES = 60 * 24;

    /** Параметр адреса личного кабинета с результатом подтверждения. */
    private const string RESULT_PARAMETER = 'verified';

    /** Значение параметра: адрес подтверждён. */
    private const string RESULT_CONFIRMED = '1';

    /** Значение параметра: ссылка не подошла к текущему адресу. */
    private const string RESULT_REJECTED = '0';

    /**
     * @param UserRepository  $users    пользователи
     * @param UserService     $accounts подтверждение адреса
     * @param MailFactory     $mail     отправка писем
     * @param UrlGenerator    $urls     подписанные адреса
     * @param SettingsService $settings название сайта для письма
     * @param Application     $app      текущий язык
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserService $accounts,
        private readonly MailFactory $mail,
        private readonly UrlGenerator $urls,
        private readonly SettingsService $settings,
        private readonly Application $app,
    ) {
    }

    /**
     * Ставит в очередь письмо со ссылкой, если адрес ещё не подтверждён.
     *
     * @param string|null $locale язык письма; по умолчанию язык текущего запроса
     */
    public function send(User $user, ?string $locale = null): void
    {
        if ($user->getEmailVerifiedAt() !== null) {
            return;
        }

        $url = $this->urls->temporarySignedRoute(
            'verification.verify',
            new DateTimeImmutable('+' . self::LINK_LIFETIME_MINUTES . ' minutes'),
            ['id' => $user->getAuthIdentifier(), 'hash' => self::hash($user->getEmail())],
        );
        $mail = new VerifyEmailMail($url, $this->settings->siteName(), $locale ?? $this->app->getLocale());

        $this->mail->mailer()->to($user->getEmail())->queue($mail);
    }

    /**
     * Подтверждает адрес по ссылке. Подпись проверяет маршрут; здесь — что ссылка выдана для текущего email.
     *
     * @return bool подтверждён ли адрес
     */
    public function verify(int $id, string $hash): bool
    {
        $user = $this->users->find($id);

        if ($user === null || ! hash_equals(self::hash($user->getEmail()), $hash)) {
            return false;
        }

        $this->accounts->verifyEmail($user);

        return true;
    }

    /**
     * Путь возврата после перехода по ссылке из письма: личный кабинет с признаком результата подтверждения.
     */
    public function verifiedRedirectUrl(bool $verified): string
    {
        $result = $verified ? self::RESULT_CONFIRMED : self::RESULT_REJECTED;

        return SiteUrls::ACCOUNT_PATH . '?' . self::RESULT_PARAMETER . '=' . $result;
    }

    /**
     * Отпечаток адреса для ссылки: меняется вместе с адресом, поэтому старая ссылка перестаёт подходить.
     */
    private static function hash(string $email): string
    {
        return sha1(mb_strtolower($email));
    }
}
