<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\DTO\Localization\LocaleData;
use App\DTO\Seo\AlternateUrlData;
use App\Services\Localization\LocalizationService;
use Illuminate\Support\Collection;

/**
 * Адреса страниц публичного сайта: пути для ссылок и абсолютные адреса для canonical, Open Graph, hreflang,
 * карты сайта и RSS. Основа абсолютных адресов — APP_URL: при отрисовке на сервере Nuxt обращается к API
 * по внутреннему имени.
 */
final class SiteUrls
{
    /** Страница входа. */
    public const string LOGIN_PATH = '/login';

    /** Личный кабинет. */
    public const string ACCOUNT_PATH = '/account';

    /** Завершение регистрации после входа через соцсеть. */
    public const string SOCIAL_REGISTRATION_PATH = '/register/social';

    /** Лента RSS. */
    public const string RSS_PATH = '/rss.xml';

    /** Параметр страницы входа: вход через соцсеть не удался. */
    public const string SOCIAL_ERROR_QUERY = 'social_error';

    /** Префикс адреса статьи: к нему добавляется числовой идентификатор. */
    private const string ARTICLES_PATH = '/articles/';

    /** Префикс адреса альбома: к нему добавляется UUID. */
    private const string ALBUMS_PATH = '/albums/';

    /** Префикс адреса карточки автора: к нему добавляется числовой идентификатор. */
    private const string AUTHORS_PATH = '/authors/';

    /** Адрес ленты: главная страница, фильтр по разделу передаётся в строке запроса. */
    private const string FEED_PATH = '/';

    /** Значение hreflang для версии по умолчанию. */
    private const string DEFAULT_HREFLANG = 'x-default';

    /**
     * @param LocalizationService $localization языки сайта
     * @param string              $baseUrl      адрес сайта из APP_URL
     */
    public function __construct(
        private readonly LocalizationService $localization,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * Путь страницы статьи.
     */
    public static function articlePath(int $id): string
    {
        return self::ARTICLES_PATH . $id;
    }

    /**
     * Путь страницы альбома по UUID.
     */
    public static function albumPath(string $uuid): string
    {
        return self::ALBUMS_PATH . $uuid;
    }

    /**
     * Путь карточки автора.
     */
    public static function authorPath(int $id): string
    {
        return self::AUTHORS_PATH . $id;
    }

    /**
     * Путь ленты, отфильтрованной по разделу.
     */
    public static function sectionPath(string $slug): string
    {
        return self::FEED_PATH . '?' . http_build_query(['section' => $slug]);
    }

    /**
     * Адрес сайта без завершающей косой черты.
     */
    public function base(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Абсолютный адрес страницы на языке по умолчанию или на указанном языке.
     */
    public function to(string $path, ?string $language = null): string
    {
        $config = $this->localization->config();
        $path = '/' . ltrim($path, '/');

        if ($language === null || $language === $config->default) {
            return $this->base() . $path;
        }

        return $config->mode->localize($this->base(), $path, $language, $config->baseDomain);
    }

    /**
     * Языковые версии страницы для hreflang; пусто, если язык один.
     *
     * @return Collection<int, AlternateUrlData>
     */
    public function alternates(string $path): Collection
    {
        if (! $this->localization->isMultilingual()) {
            return collect();
        }

        return $this->localization->config()->languages
            ->map(fn (LocaleData $language): AlternateUrlData => new AlternateUrlData(
                $language->code,
                $this->to($path, $language->code),
            ))
            ->push(new AlternateUrlData(self::DEFAULT_HREFLANG, $this->to($path)));
    }
}
