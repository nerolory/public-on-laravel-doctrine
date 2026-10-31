<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Analytics\AnalyticsDayData;
use App\Enums\Settings\Counter;
use App\Enums\Settings\SettingKey;
use App\Services\Analytics\AnalyticsReports;
use Illuminate\Support\Collection;

/**
 * Источник отчёта счётчика по его API (стратегия). Новый счётчик добавляется отдельным классом
 * и регистрируется в {@see AnalyticsReports}.
 */
interface AnalyticsProvider
{
    /** Длина периода отчёта в днях: месяц — стандартный период отчётов Метрики и GA4. */
    public const int DAYS = 30;

    /**
     * Счётчик, чей отчёт строит провайдер.
     */
    public function key(): Counter;

    /**
     * Настройки, без которых API недоступен: идентификаторы и ключи доступа.
     *
     * @return Collection<int, SettingKey>
     */
    public function requiredSettings(): Collection;

    /**
     * Показатели по дням за период, по возрастанию даты.
     *
     * @return Collection<int, AnalyticsDayData>
     */
    public function days(): Collection;
}
