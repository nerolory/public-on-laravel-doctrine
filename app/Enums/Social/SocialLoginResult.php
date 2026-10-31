<?php

declare(strict_types=1);

namespace App\Enums\Social;

use App\Support\Seo\SiteUrls;

/**
 * Итог входа через соцсеть. Значение уходит фронтенду в поле next ответа Telegram-входа.
 */
enum SocialLoginResult: string
{
    /** Вход выполнен, пользователь найден. */
    case LoggedIn = 'logged_in';

    /** Нужно завершить регистрацию через соцсеть. */
    case Pending = 'pending';

    /** Вход не удался. */
    case Failed = 'failed';

    /**
     * Страница фронтенда, куда ведёт возврат от OAuth-провайдера.
     */
    public function redirectPath(): string
    {
        return match ($this) {
            self::LoggedIn => SiteUrls::ACCOUNT_PATH,
            self::Pending => SiteUrls::SOCIAL_REGISTRATION_PATH,
            self::Failed => SiteUrls::LOGIN_PATH . '?' . http_build_query([SiteUrls::SOCIAL_ERROR_QUERY => 1]),
        };
    }
}
