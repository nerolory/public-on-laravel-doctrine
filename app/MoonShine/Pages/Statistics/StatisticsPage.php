<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Statistics;

use App\DTO\Admin\AdminLinkData;
use App\DTO\Admin\AdminRankRowData;
use App\DTO\Admin\AdminStatisticTotalData;
use App\DTO\Media\ChartData;
use App\DTO\Media\ChartSeriesData;
use App\DTO\Statistics\TopArticleData;
use App\DTO\Statistics\TopReferrerData;
use App\Enums\User\Ability;
use App\Services\Statistics\StatisticsService;
use App\Support\Seo\SiteUrls;
use App\Traits\AuthorizesAdminPage;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Collection;
use LogicException;
use MoonShine\Apexcharts\Components\LineChartMetric;
use MoonShine\Apexcharts\Support\SeriesItem;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Статистика сайта по собственным данным: для владельца и администраторов.
 */
final class StatisticsPage extends Page
{
    use AuthorizesAdminPage;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Показатели итогов периода в порядке вывода. */
    private const array TOTAL_KEYS = ['views', 'visitors', 'registrations', 'articles', 'comments'];

    /** Адрес страницы в админке: /admin/page/statistics. */
    protected ?string $alias = 'statistics';

    /**
     * @param CoreContract      $core       ядро MoonShine
     * @param StatisticsService $statistics отчёты
     * @param Gate              $gate       права доступа
     * @param ViewFactory       $views      шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly StatisticsService $statistics,
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
        return __('admin.statistics.title');
    }

    /**
     * Хлебные крошки: админка → статистика.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление пользователями.
     */
    protected function ability(): Ability
    {
        return Ability::ManageUsers;
    }

    /**
     * Отчёт за период из параметра ?days=: переключатель периодов, итоги, графики и самое читаемое.
     *
     * @return iterable<FlexibleRender|LineChartMetric>
     */
    protected function components(): iterable
    {
        $days = (int) $this->query('days', StatisticsService::DEFAULT_PERIOD);

        if (! in_array($days, StatisticsService::PERIODS, strict: true)) {
            throw new EntityNotFoundException('Statistics period is not supported: ' . $days);
        }

        $report = $this->statistics->report($days);

        return [
            FlexibleRender::make($this->views->make('admin.statistics', [
                'periods' => $this->periodLinks($report->period),
                'totals' => $this->totalRows($report->totals),
            ])),
            self::lineChart($report->traffic),
            self::lineChart($report->activity),
            FlexibleRender::make($this->views->make('admin.statistics-tops', [
                'topArticles' => $this->topArticleRows($report->topArticles),
                'topReferrers' => $this->topReferrerRows($report->topReferrers),
            ])),
        ];
    }

    /**
     * Ссылки на периоды; текущий период отмечен.
     *
     * @return Collection<int, AdminLinkData>
     */
    private function periodLinks(int $current): Collection
    {
        return collect(StatisticsService::PERIODS)->map(
            fn (int $period): AdminLinkData => new AdminLinkData(
                label: __('admin.statistics.days', ['count' => $period]),
                url: $this->getRoute(['days' => $period]),
                active: $period === $current,
            ),
        );
    }

    /**
     * Итоги периода в порядке TOTAL_KEYS; итог без значения означает ошибку в отчёте.
     *
     * @param Collection<string, int> $totals
     *
     * @return Collection<int, AdminStatisticTotalData>
     */
    private function totalRows(Collection $totals): Collection
    {
        return collect(self::TOTAL_KEYS)->map(
            static function (string $key) use ($totals): AdminStatisticTotalData {
                $total = $totals->get($key) ?? throw new LogicException('Statistics total is missing: ' . $key);

                return new AdminStatisticTotalData(
                    label: __('admin.statistics.' . $key),
                    value: number_format(
                        num: $total,
                        decimals: 0,
                        decimal_separator: ',',
                        thousands_separator: ' ',
                    ),
                );
            },
        );
    }

    /**
     * Строки топа статей: название, адрес на сайте и число просмотров.
     *
     * @param Collection<int, TopArticleData> $articles
     *
     * @return Collection<int, AdminRankRowData>
     */
    private function topArticleRows(Collection $articles): Collection
    {
        return $articles->map(
            static fn (TopArticleData $article): AdminRankRowData => new AdminRankRowData(
                label: $article->title,
                url: SiteUrls::articlePath($article->id),
                count: $article->views,
            ),
        );
    }

    /**
     * Строки топа источников переходов: домен без ссылки.
     *
     * @param Collection<int, TopReferrerData> $referrers
     *
     * @return Collection<int, AdminRankRowData>
     */
    private function topReferrerRows(Collection $referrers): Collection
    {
        return $referrers->map(
            static fn (TopReferrerData $referrer): AdminRankRowData => new AdminRankRowData(
                label: $referrer->host,
                url: null,
                count: $referrer->views,
            ),
        );
    }

    /**
     * Линейный график MoonShine: ряды по подписям дней в исходном порядке.
     */
    private static function lineChart(ChartData $chart): LineChartMetric
    {
        $series = $chart->series->map(static fn (ChartSeriesData $series): SeriesItem => SeriesItem::make(
            $series->name,
            $chart->labels->combine($series->values)->all(),
        ));

        return LineChartMetric::make($chart->title)->series($series->all())->withoutSortKeys();
    }
}
