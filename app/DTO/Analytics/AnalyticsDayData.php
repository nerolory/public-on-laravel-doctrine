<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

/**
 * Показатели счётчика за день.
 */
final readonly class AnalyticsDayData
{
    /**
     * @param string $date      дата YYYY-MM-DD
     * @param int    $visits    визиты (сеансы)
     * @param int    $users     посетители
     * @param int    $pageviews просмотры страниц
     */
    public function __construct(
        public string $date,
        public int $visits,
        public int $users,
        public int $pageviews,
    ) {
    }
}
