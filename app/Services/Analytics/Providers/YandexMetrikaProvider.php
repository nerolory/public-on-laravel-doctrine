<?php

declare(strict_types=1);

namespace App\Services\Analytics\Providers;

use App\Contracts\AnalyticsProvider;
use App\DTO\Analytics\AnalyticsDayData;
use App\Enums\Settings\Counter;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Collection;
use UnexpectedValueException;

/**
 * Яндекс Метрика: Stat API с OAuth-токеном.
 */
final class YandexMetrikaProvider implements AnalyticsProvider
{
    /** Ожидание ответа Stat API в секундах: отчёт строится на странице админки, дольше ждать её не стоит. */
    private const int TIMEOUT = 10;

    /** Визиты, посетители и просмотры — в этом порядке приходят значения строки отчёта. */
    private const string METRICS = 'ym:s:visits,ym:s:users,ym:s:pageviews';

    /** Группировка по дню визита. */
    private const string DIMENSION = 'ym:s:date';

    /**
     * @param SettingsService $settings настройки счётчика
     * @param Http            $http     HTTP-клиент
     * @param string          $statUrl  адрес Stat API из config/services.php
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Http $http,
        private readonly string $statUrl,
    ) {
    }

    /**
     * Ключ счётчика.
     */
    public function key(): Counter
    {
        return Counter::Yandex;
    }

    /**
     * Номер счётчика и OAuth-токен.
     *
     * @return Collection<int, SettingKey>
     */
    public function requiredSettings(): Collection
    {
        return collect([SettingKey::MetricsYandexId, SettingKey::MetricsYandexToken]);
    }

    /**
     * Показатели по дням из Stat API.
     *
     * @return Collection<int, AnalyticsDayData>
     */
    public function days(): Collection
    {
        $token = $this->settings->string(SettingKey::MetricsYandexToken);
        $response = $this->http->withHeaders(['Authorization' => 'OAuth ' . $token])
            ->timeout(self::TIMEOUT)
            ->get($this->statUrl, [
                'ids' => $this->settings->string(SettingKey::MetricsYandexId),
                'metrics' => self::METRICS,
                'dimensions' => self::DIMENSION,
                'date1' => self::DAYS . 'daysAgo',
                'date2' => 'today',
                'sort' => self::DIMENSION,
            ])
            ->throw()
            ->json();

        $data = $response['data'] ?? throw new UnexpectedValueException('Yandex Metrika returned no data field.');

        return collect($data)->map(static fn (array $row): AnalyticsDayData => new AnalyticsDayData(
            date: (string) $row['dimensions'][0]['name'],
            visits: (int) $row['metrics'][0],
            users: (int) $row['metrics'][1],
            pageviews: (int) $row['metrics'][2],
        ));
    }
}
