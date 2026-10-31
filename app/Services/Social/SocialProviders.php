<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\DTO\Seo\ShareLinkData;
use App\Enums\Settings\SettingKey;
use App\Enums\Social\SocialProvider;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Провайдеры входа через соцсети. Каждый включается и настраивается в админке;
 * ключи приложений подставляются в конфигурацию Socialite перед вызовом драйвера.
 */
final class SocialProviders
{
    /** Страница фронтенда, куда Telegram возвращает результат во фрагменте #tgAuthResult. */
    private const string TELEGRAM_RETURN_PATH = '/auth/telegram';

    /** Адрес страницы авторизации Telegram из конфигурации. */
    private readonly string $telegramLoginPage;

    /**
     * @param SettingsService $settings        ключи и включение провайдеров
     * @param Config          $config          конфигурация Socialite
     * @param UrlGenerator    $urls            адреса возврата
     * @param string          $telegramAuthUrl страница авторизации Telegram из config/services.php
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Config $config,
        private readonly UrlGenerator $urls,
        string $telegramAuthUrl,
    ) {
        $this->telegramLoginPage = $telegramAuthUrl;
    }

    /**
     * Кнопки включённых провайдеров для страниц входа и регистрации; Telegram последним.
     *
     * @return Collection<int, ShareLinkData>
     */
    public function links(): Collection
    {
        return collect(SocialProvider::cases())
            ->filter(fn (SocialProvider $provider): bool => $this->isEnabled($provider))
            ->map(fn (SocialProvider $provider): ShareLinkData => new ShareLinkData(
                $provider->value,
                $this->loginUrl($provider),
            ))
            ->values();
    }

    /**
     * Включён ли провайдер и задан ли ключ, без которого вход не работает.
     */
    public function isEnabled(SocialProvider $provider): bool
    {
        return $this->settings->boolean(SettingKey::socialEnabled($provider))
            && $this->settings->optionalString(SettingKey::socialCredential($provider)) !== null;
    }

    /**
     * Включён ли вход через OAuth-провайдера; Telegram входит своим способом.
     */
    public function isOAuthEnabled(SocialProvider $provider): bool
    {
        return $provider->isOAuth() && $this->isEnabled($provider);
    }

    /**
     * Переносит ключи OAuth-провайдера из настроек сайта в config/services для Socialite;
     * что вход включён, проверяет SitePolicy на маршруте.
     */
    public function configure(SocialProvider $provider): void
    {
        $this->config->set('services.' . $provider->value, [
            'client_id' => $this->settings->string(SettingKey::socialCredential($provider)),
            'client_secret' => $this->settings->optionalString(SettingKey::socialClientSecret($provider)),
            'redirect' => $this->urls->route('social.callback', ['provider' => $provider->value]),
        ]);
    }

    /**
     * Токен бота для проверки подписи Telegram.
     */
    public function telegramBotToken(): string
    {
        return $this->settings->string(SettingKey::socialCredential(SocialProvider::Telegram));
    }

    /**
     * Адрес авторизации Telegram: результат вернётся на страницу фронтенда во фрагменте #tgAuthResult.
     *
     * @throws LogicException токен бота задан без идентификатора бота до двоеточия
     */
    public function telegramAuthUrl(): string
    {
        $token = $this->telegramBotToken();

        if (! str_contains($token, ':')) {
            throw new LogicException('The Telegram bot token has no bot id before the colon.');
        }

        $botId = strstr($token, ':', before_needle: true);
        $origin = rtrim($this->urls->to('/'), '/');

        return $this->telegramLoginPage . '?' . http_build_query([
            'bot_id' => $botId,
            'origin' => $origin,
            'request_access' => 'write',
            'return_to' => $origin . self::TELEGRAM_RETURN_PATH,
        ]);
    }

    /**
     * Адрес входа через провайдера: OAuth — маршрут перенаправления, Telegram — страница авторизации.
     */
    private function loginUrl(SocialProvider $provider): string
    {
        return $provider->isOAuth()
            ? $this->urls->route('social.redirect', ['provider' => $provider->value])
            : $this->telegramAuthUrl();
    }
}
