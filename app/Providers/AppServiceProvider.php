<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AccountSelfDeletion;
use App\Contracts\UserRepository as UserRepositoryContract;
use App\Entities\Album;
use App\Entities\Article;
use App\Entities\Chart;
use App\Entities\Comment;
use App\Entities\Poll;
use App\Entities\User;
use App\Enums\Http\RateLimit;
use App\Enums\User\Ability;
use App\Listeners\Mail\ApplyMailSettings;
use App\MoonShine\Layouts\MoonShineLayout;
use App\MoonShine\Pages\Article\ArticleFormPage;
use App\Policies\AlbumPolicy;
use App\Policies\ArticlePolicy;
use App\Policies\ChartPolicy;
use App\Policies\CommentPolicy;
use App\Policies\PollPolicy;
use App\Policies\SitePolicy;
use App\Policies\UserPolicy;
use App\Repositories\UserRepository;
use App\Services\Analytics\Providers\Ga4Provider;
use App\Services\Analytics\Providers\YandexMetrikaProvider;
use App\Services\Article\ArticleContent;
use App\Services\Article\ArticleViewService;
use App\Services\Seo\PublicFilesService;
use App\Services\Seo\SeoService;
use App\Services\Seo\ShareService;
use App\Services\Seo\SiteConfigService;
use App\Services\Settings\SettingsSchema;
use App\Services\Settings\SettingsService;
use App\Services\Social\SocialProviders;
use App\Services\User\AccountDeletionService;
use App\Support\Localization\LanguageFiles;
use App\Support\Media\MediaStorage;
use App\Support\Seo\SiteUrls;
use Closure;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\VKID\VKIDExtendSocialite;
use SocialiteProviders\Yandex\YandexExtendSocialite;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;

/**
 * Привязки контейнера: реализации контрактов, значения из конфигурации, службы из настроек,
 * правила доступа и ограничители частоты запросов.
 */
class AppServiceProvider extends ServiceProvider
{
    /** Провайдеры входа из SocialiteProviders: регистрируются через событие Socialite. */
    private const array SOCIALITE_PROVIDERS = [
        VKIDExtendSocialite::class,
        YandexExtendSocialite::class,
        AppleExtendSocialite::class,
    ];

    /** Каталог сборки ассетов админки в public (npm run build:admin). */
    private const string ADMIN_BUILD_DIRECTORY = 'build/admin';

    /** Карта сайта в storage: каталог закрыт от веб-сервера, файл отдаёт маршрут /sitemap.xml. */
    private const string SITEMAP_PATH = 'app/sitemap.xml';

    /** Каталог медиафайлов локального диска в public: картинки отдаёт веб-сервер напрямую. */
    private const string MEDIA_DIRECTORY = 'media';


    /** Политики доступа к сущностям. */
    private const array POLICIES = [
        Album::class => AlbumPolicy::class,
        Article::class => ArticlePolicy::class,
        Chart::class => ChartPolicy::class,
        Comment::class => CommentPolicy::class,
        Poll::class => PollPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Регистрирует привязки.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryContract::class, UserRepository::class);
        $this->app->bind(AccountSelfDeletion::class, AccountDeletionService::class);
        $this->registerConfigValues();
        $this->registerMedia();
        $this->app->singleton(LanguageFiles::class, static fn (Application $app): LanguageFiles => new LanguageFiles(
            $app->make(Filesystem::class),
            lang_path(),
        ));
    }

    /**
     * Почта, провайдеры входа, правила доступа и ограничители частоты.
     */
    public function boot(Dispatcher $events, Gate $gate): void
    {
        $this->registerAuthorization($gate);
        // Ограничители регистрируются при создании RateLimiter: его хранилище берётся из конфигурации на этот момент.
        $this->app->afterResolving(RateLimiter::class, $this->registerRateLimits(...));

        // Почта настраивается в админке. Настройки применяются при создании почтового менеджера;
        // в обработчике очереди их применяет слушатель ApplyMailSettings перед каждой задачей.
        $this->app->afterResolving('mail.manager', function (): void {
            $this->app->make(ApplyMailSettings::class)->apply();
        });

        foreach (self::SOCIALITE_PROVIDERS as $listener) {
            $events->listen(SocialiteWasCalled::class, [$listener, 'handle']);
        }
    }

    /**
     * Политики сущностей и права, не привязанные к записи.
     */
    private function registerAuthorization(Gate $gate): void
    {
        foreach (self::POLICIES as $entity => $policy) {
            $gate->policy($entity, $policy);
        }
        foreach (Ability::cases() as $ability) {
            $gate->define($ability->value, [SitePolicy::class, Str::camel($ability->value)]);
        }
    }

    /**
     * Именованные ограничители частоты: лимит на пользователя, у гостя — на IP.
     */
    private function registerRateLimits(RateLimiter $limiter): void
    {
        foreach (RateLimit::cases() as $limit) {
            $limiter->for($limit->value, static fn (Request $request): Limit => Limit::perMinute($limit->perMinute())
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }
    }

    /**
     * Значения из конфигурации для конструкторов служб.
     */
    private function registerConfigValues(): void
    {
        $this->app->when(ArticleViewService::class)->needs('$secret')->giveConfig('app.key');
        $this->app->when(SiteUrls::class)->needs('$baseUrl')->giveConfig('app.url');
        $this->app->when(SettingsSchema::class)->needs('$defaultFromAddress')->giveConfig('mail.from.address');
        $this->app->when(SocialProviders::class)->needs('$telegramAuthUrl')
            ->giveConfig('services.telegram_login.auth_url');
        $this->app->when(ShareService::class)->needs('$templates')->giveConfig('services.share');
        $this->app->when(SeoService::class)->needs('$territories')->giveConfig('seo.og_territories');
        $this->app->when(ArticleContent::class)->needs(HtmlSanitizer::class)
            ->give(static fn (): HtmlSanitizer => new HtmlSanitizer(ArticleContent::sanitizerConfig()));
        // Клиент GA4 создаётся фабрикой при каждом обращении: ключ сервисного аккаунта берётся из настроек.
        $this->app->when(Ga4Provider::class)->needs(Closure::class)->give(static fn (): Closure => static fn (
            array $credentials,
        ): BetaAnalyticsDataClient => new BetaAnalyticsDataClient([
            'credentials' => $credentials,
            'transport' => 'rest',
        ]));
        $this->app->when(SiteConfigService::class)->needs('$scripts')->giveConfig('services.counters');
        $this->app->when(YandexMetrikaProvider::class)->needs('$statUrl')
            ->giveConfig('services.yandex_metrika.stat_url');
        $this->app->when(PublicFilesService::class)->needs('$sitemapPath')
            ->give(static fn (): string => storage_path(self::SITEMAP_PATH));
        $this->app->when([MoonShineLayout::class, ArticleFormPage::class])->needs(Vite::class)
            ->give(static fn (Application $app): Vite => (clone $app->make(Vite::class))
                ->useBuildDirectory(self::ADMIN_BUILD_DIRECTORY));
    }

    /**
     * Обработка картинок и хранилище медиафайлов.
     */
    private function registerMedia(): void
    {
        $this->app->singleton(
            ImageManagerInterface::class,
            static fn (): ImageManagerInterface => ImageManager::usingDriver(
                GdDriver::class,
                autoOrientation: true,
                decodeAnimation: false,
                strip: true,
            ),
        );
        // Диск собирается из настроек, поэтому живёт не дольше запроса или задачи очереди.
        $this->app->scoped(MediaStorage::class, static fn (Application $app): MediaStorage => new MediaStorage(
            $app->make(FilesystemManager::class),
            $app->make(SettingsService::class),
            public_path(self::MEDIA_DIRECTORY),
        ));
    }
}
