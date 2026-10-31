<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Analytics;

use App\DTO\Admin\AdminAnalyticsSectionData;
use App\DTO\Analytics\AnalyticsReportData;
use App\Enums\User\Ability;
use App\Services\Analytics\AnalyticsReports;
use App\Traits\AuthorizesAdminPage;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Отчёты подключённых счётчиков за 30 дней: для владельца и администраторов.
 */
final class AnalyticsPage extends Page
{
    use AuthorizesAdminPage;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/analytics. */
    protected ?string $alias = 'analytics';

    /**
     * @param CoreContract     $core    ядро MoonShine
     * @param AnalyticsReports $reports отчёты счётчиков
     * @param Gate             $gate    права доступа
     * @param ViewFactory      $views   шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly AnalyticsReports $reports,
        private readonly Gate $gate,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.analytics.title');
    }

    /**
     * Хлебные крошки: админка → аналитика.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление настройками.
     */
    protected function ability(): Ability
    {
        return Ability::ManageSettings;
    }

    /**
     * Отчёты по счётчикам.
     *
     * @return iterable<FlexibleRender>
     */
    protected function components(): iterable
    {
        return [FlexibleRender::make($this->views->make('admin.analytics', [
            'reports' => $this->reports->all()->map($this->section(...)),
        ]))];
    }

    /**
     * Блок отчёта: подпись счётчика, итоги, дни или сокращённая ошибка.
     */
    private function section(AnalyticsReportData $report): AdminAnalyticsSectionData
    {
        return new AdminAnalyticsSectionData(
            title: __('admin.analytics.providers.' . $report->provider),
            report: $report,
        );
    }
}
