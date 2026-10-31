<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use App\DTO\Complaint\ComplaintReasonData;
use App\DTO\Settings\FormLimitsData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Публичные настройки сайта для фронтенда.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SiteConfigData implements Arrayable
{
    /**
     * @param string                               $siteName         название сайта
     * @param Collection<int, CounterData>         $counters         включённые счётчики
     * @param FormLimitsData                       $limits           лимиты форм
     * @param Collection<int, ComplaintReasonData> $complaintReasons причины жалобы
     */
    public function __construct(
        public string $siteName,
        public Collection $counters,
        public FormLimitsData $limits,
        public Collection $complaintReasons,
    ) {
    }

    /**
     * Настройки для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'site_name' => $this->siteName,
            'counters' => $this->counters->toArray(),
            'limits' => $this->limits->toArray(),
            'complaint_reasons' => $this->complaintReasons->values()->toArray(),
        ];
    }
}
