<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

use Illuminate\Support\Collection;

/**
 * Отчёт одного счётчика за период или ошибка его API.
 */
final readonly class AnalyticsReportData
{
    /**
     * @param string                            $provider  ключ счётчика: yandex или ga4
     * @param Collection<int, AnalyticsDayData> $days      показатели по дням
     * @param int                               $visits    визитов за период
     * @param int                               $users     посетителей за период
     * @param int                               $pageviews просмотров страниц за период
     * @param string|null                       $error     ошибка API, отчёт пуст
     */
    public function __construct(
        public string $provider,
        public Collection $days,
        public int $visits,
        public int $users,
        public int $pageviews,
        public ?string $error = null,
    ) {
    }
}
