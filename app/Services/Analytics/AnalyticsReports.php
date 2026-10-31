<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Contracts\AnalyticsProvider;
use App\DTO\Analytics\AnalyticsDayData;
use App\DTO\Analytics\AnalyticsReportData;
use App\Enums\Settings\SettingKey;
use App\Services\Analytics\Providers\Ga4Provider;
use App\Services\Analytics\Providers\YandexMetrikaProvider;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Отчёты подключённых счётчиков за 30 дней по их API. Ответы кешируются на час.
 * Ошибка API выводится в отчёте админки, страница при этом открывается.
 */
final class AnalyticsReports
{
    /** Время жизни отчёта в кеше в секундах: API счётчиков ограничивают число запросов. */
    private const int CACHE_TTL = 3600;

    /** Длина текста ошибки API в отчёте: длинный ответ счётчика не растягивает страницу админки. */
    private const int ERROR_LENGTH = 300;

    /** @var Collection<int, AnalyticsProvider> */
    private Collection $providers;

    /**
     * Порядок счётчиков задаёт порядок отчётов на странице.
     *
     * @param YandexMetrikaProvider $yandex   Яндекс Метрика
     * @param Ga4Provider           $ga4      Google Analytics 4
     * @param SettingsService       $settings включён ли счётчик
     * @param Cache                 $cache    кеш отчётов
     */
    public function __construct(
        YandexMetrikaProvider $yandex,
        Ga4Provider $ga4,
        private readonly SettingsService $settings,
        private readonly Cache $cache,
    ) {
        $this->providers = collect([$yandex, $ga4]);
    }

    /**
     * Отчёты включённых и настроенных счётчиков.
     *
     * @return Collection<int, AnalyticsReportData>
     */
    public function all(): Collection
    {
        return $this->providers
            ->filter($this->configured(...))
            ->map($this->report(...))
            ->values();
    }

    /**
     * Счётчик включён и все обязательные настройки заполнены.
     */
    private function configured(AnalyticsProvider $provider): bool
    {
        if (! $this->settings->counterEnabled($provider->key())) {
            return false;
        }

        return $provider->requiredSettings()
            ->every(fn (SettingKey $key): bool => $this->settings->optionalString($key) !== null);
    }

    /**
     * Отчёт из кеша или из API; ошибка API становится текстом в отчёте.
     */
    private function report(AnalyticsProvider $provider): AnalyticsReportData
    {
        try {
            /** @var Collection<int, AnalyticsDayData> $days */
            $days = $this->cache->remember(
                'analytics.' . $provider->key()->value,
                self::CACHE_TTL,
                $provider->days(...),
            );

            return new AnalyticsReportData(
                provider: $provider->key()->value,
                days: $days,
                visits: (int) $days->sum('visits'),
                users: (int) $days->sum('users'),
                pageviews: (int) $days->sum('pageviews'),
            );
        } catch (Throwable $exception) {
            return new AnalyticsReportData(
                provider: $provider->key()->value,
                days: collect(),
                visits: 0,
                users: 0,
                pageviews: 0,
                error: Str::limit($exception->getMessage(), self::ERROR_LENGTH),
            );
        }
    }
}
