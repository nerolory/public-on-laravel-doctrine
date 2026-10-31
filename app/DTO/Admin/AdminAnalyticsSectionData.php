<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use App\DTO\Analytics\AnalyticsReportData;

/**
 * Блок счётчика аналитики в админке: подпись и отчёт либо текст ошибки внутри отчёта.
 */
final readonly class AdminAnalyticsSectionData
{
    /**
     * @param string              $title  подпись счётчика
     * @param AnalyticsReportData $report отчёт счётчика или ошибка его API
     */
    public function __construct(
        public string $title,
        public AnalyticsReportData $report,
    ) {
    }
}
