<?php

declare(strict_types=1);

namespace App\DTO\Statistics;

use App\DTO\Media\ChartData;
use Illuminate\Support\Collection;

/**
 * Статистика сайта за период: итоги, графики и топы.
 */
final readonly class StatisticsReportData
{
    /**
     * @param int                              $period       длина периода в днях
     * @param Collection<string, int>          $totals       views, visitors, registrations, articles, comments
     * @param ChartData                        $traffic      график просмотров и посетителей
     * @param ChartData                        $activity     регистрации/статьи/комментарии
     * @param Collection<int, TopArticleData>  $topArticles  самые читаемые статьи
     * @param Collection<int, TopReferrerData> $topReferrers главные источники переходов
     */
    public function __construct(
        public int $period,
        public Collection $totals,
        public ChartData $traffic,
        public ChartData $activity,
        public Collection $topArticles,
        public Collection $topReferrers,
    ) {
    }
}
