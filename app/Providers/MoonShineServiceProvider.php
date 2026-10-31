<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\Album\AlbumIndexPage;
use App\MoonShine\Pages\Analytics\AnalyticsPage;
use App\MoonShine\Pages\Article\ArticleFormPage;
use App\MoonShine\Pages\Article\ArticleIndexPage;
use App\MoonShine\Pages\ArticleSection\ArticleSectionFormPage;
use App\MoonShine\Pages\ArticleSection\ArticleSectionIndexPage;
use App\MoonShine\Pages\Complaint\ComplaintIndexPage;
use App\MoonShine\Pages\Legal\LegalDocumentFormPage;
use App\MoonShine\Pages\Legal\LegalDocumentIndexPage;
use App\MoonShine\Pages\Localization\LanguageFormPage;
use App\MoonShine\Pages\Localization\LanguageIndexPage;
use App\MoonShine\Pages\Reaction\ConflictRatingPage;
use App\MoonShine\Pages\Settings\SiteSettingsPage;
use App\MoonShine\Pages\Statistics\StatisticsPage;
use App\MoonShine\Pages\User\RoleIndexPage;
use App\MoonShine\Pages\User\UserFormPage;
use App\MoonShine\Pages\User\UserIndexPage;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

/**
 * Страницы админки MoonShine.
 */
class MoonShineServiceProvider extends ServiceProvider
{
    /** Страницы проекта в дополнение к стандартным страницам MoonShine. */
    private const array PAGES = [
        ArticleIndexPage::class,
        ArticleFormPage::class,
        ArticleSectionIndexPage::class,
        ArticleSectionFormPage::class,
        AlbumIndexPage::class,
        ConflictRatingPage::class,
        ComplaintIndexPage::class,
        AnalyticsPage::class,
        StatisticsPage::class,
        UserIndexPage::class,
        UserFormPage::class,
        RoleIndexPage::class,
        SiteSettingsPage::class,
        LanguageIndexPage::class,
        LanguageFormPage::class,
        LegalDocumentIndexPage::class,
        LegalDocumentFormPage::class,
    ];

    /**
     * Регистрирует страницы.
     *
     * @param CoreContract<MoonShineConfigurator> $core ядро MoonShine
     */
    public function boot(CoreContract $core): void
    {
        $core->pages([...$core->getConfig()->getPages(), ...self::PAGES]);
    }
}
