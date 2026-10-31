<?php

declare(strict_types=1);

namespace App\DTO\Legal;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Условия регистрации для публичной части сайта.
 * Регион, возраст, баннер cookies и документы.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LegalTermsData implements Arrayable
{
    /**
     * @param string                             $region       ключ правового региона
     * @param int                                $minimumAge   возраст для регистрации
     * @param bool                               $cookieBanner нужен ли баннер cookies
     * @param bool                               $dataExport   доступна ли выгрузка данных
     * @param Collection<int, LegalDocumentData> $documents    обязательные первыми
     */
    public function __construct(
        public string $region,
        public int $minimumAge,
        public bool $cookieBanner,
        public bool $dataExport,
        public Collection $documents,
    ) {
    }

    /**
     * Условия для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'region' => $this->region,
            'minimum_age' => $this->minimumAge,
            'cookie_banner' => $this->cookieBanner,
            'data_export' => $this->dataExport,
            'documents' => $this->documents->values()->toArray(),
        ];
    }
}
