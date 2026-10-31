<?php

declare(strict_types=1);

namespace App\Services\Analytics\Providers;

use App\Contracts\AnalyticsProvider;
use App\DTO\Analytics\AnalyticsDayData;
use App\Enums\Settings\Counter;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;
use Closure;
use DateTimeImmutable;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\Row;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Collection;
use UnexpectedValueException;

/**
 * Google Analytics 4: Data API с ключом сервисного аккаунта.
 */
final class Ga4Provider implements AnalyticsProvider
{
    /** Сеансы, посетители и просмотры — в этом порядке приходят значения строки отчёта. */
    private const array METRICS = ['sessions', 'activeUsers', 'screenPageViews'];

    /**
     * @param SettingsService                                        $settings      настройки ресурса GA4
     * @param Closure(array<string, mixed>): BetaAnalyticsDataClient $clientFactory создаёт клиент Data API по ключу
     *                                                                              сервисного аккаунта
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Closure $clientFactory,
    ) {
    }

    /**
     * Ключ счётчика.
     */
    public function key(): Counter
    {
        return Counter::Ga4;
    }

    /**
     * Ресурс GA4 и ключ сервисного аккаунта.
     *
     * @return Collection<int, SettingKey>
     */
    public function requiredSettings(): Collection
    {
        return collect([SettingKey::MetricsGa4Property, SettingKey::MetricsGa4Credentials]);
    }

    /**
     * Показатели по дням из Data API; GA4 отдаёт дни не по порядку.
     *
     * @return Collection<int, AnalyticsDayData>
     */
    public function days(): Collection
    {
        $credentials = json_decode(
            $this->settings->string(SettingKey::MetricsGa4Credentials),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        $client = ($this->clientFactory)($credentials);
        $response = $client->runReport((new RunReportRequest())
            ->setProperty('properties/' . $this->settings->string(SettingKey::MetricsGa4Property))
            ->setDateRanges([new DateRange(['start_date' => self::DAYS . 'daysAgo', 'end_date' => 'today'])])
            ->setDimensions([new Dimension(['name' => 'date'])])
            ->setMetrics(array_map(
                static fn (string $name): Metric => new Metric(['name' => $name]),
                self::METRICS,
            )));

        return collect($response->getRows())
            ->map(self::day(...))
            ->sortBy('date')
            ->values();
    }

    /**
     * День из строки отчёта; дата приходит как YYYYMMDD.
     */
    private static function day(Row $row): AnalyticsDayData
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', $row->getDimensionValues()[0]->getValue())
            ?: throw new UnexpectedValueException('GA4 returned a date that is not YYYYMMDD.');
        $values = $row->getMetricValues();

        return new AnalyticsDayData(
            date: $date->format('Y-m-d'),
            visits: (int) $values[0]->getValue(),
            users: (int) $values[1]->getValue(),
            pageviews: (int) $values[2]->getValue(),
        );
    }
}
