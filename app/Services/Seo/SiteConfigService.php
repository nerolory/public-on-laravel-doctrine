<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\DTO\Complaint\ComplaintReasonData;
use App\DTO\Seo\CounterData;
use App\DTO\Seo\SiteConfigData;
use App\Enums\Complaint\ComplaintReason;
use App\Enums\Settings\Counter;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;
use App\Support\Settings\ContentLimits;
use Illuminate\Support\Collection;

/**
 * Публичные настройки для фронтенда: название сайта, включённые счётчики, лимиты форм и причины жалоб.
 * Токены отчётов остаются на сервере. Счётчики аналитики и рекламы фронтенд загружает
 * только после согласия посетителя на соответствующие cookies.
 */
final class SiteConfigService
{
    /**
     * @param SettingsService       $settings настройки сайта
     * @param ContentLimits         $limits   лимиты форм
     * @param array<string, string> $scripts  адреса скриптов счётчиков из config/services.php;
     *                                        {id} — идентификатор счётчика
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ContentLimits $limits,
        private readonly array $scripts,
    ) {
    }

    /**
     * Настройки для фронтенда.
     */
    public function public(): SiteConfigData
    {
        return new SiteConfigData(
            siteName: $this->settings->siteName(),
            counters: $this->counters(),
            limits: $this->limits->forms(),
            complaintReasons: collect(ComplaintReason::cases())
                ->map(static fn (ComplaintReason $reason): ComplaintReasonData => new ComplaintReasonData(
                    value: $reason->value,
                    requiresText: $reason->requiresText(),
                )),
        );
    }

    /**
     * Включённые счётчики с заполненным идентификатором.
     *
     * @return Collection<int, CounterData>
     */
    private function counters(): Collection
    {
        return collect(Counter::cases())
            ->map(fn (Counter $counter): ?CounterData => $this->counter($counter))
            ->filter()
            ->values();
    }

    /**
     * Счётчик, если он включён и его идентификатор заполнен; иначе null.
     */
    private function counter(Counter $counter): ?CounterData
    {
        $id = $this->settings->optionalString(SettingKey::counterId($counter));

        if ($id === null || ! $this->settings->counterEnabled($counter)) {
            return null;
        }

        return new CounterData(
            type: $counter->value,
            id: $id,
            consent: $counter->consent(),
            script: strtr($this->scripts[$counter->value], ['{id}' => rawurlencode($id)]),
        );
    }
}
