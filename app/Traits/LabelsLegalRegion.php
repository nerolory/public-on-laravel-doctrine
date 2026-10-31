<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Подпись региона правового документа в админке.
 */
trait LabelsLegalRegion
{
    /** Ключ подписи legal.regions.* для документа без региона: он действует во всех регионах. */
    private const string ALL_REGIONS_LABEL = 'all';

    /**
     * Подпись региона; пустой регион означает документ для всех регионов.
     */
    protected function legalRegionLabel(string $region): string
    {
        return __('legal.regions.' . ($region === '' ? self::ALL_REGIONS_LABEL : $region));
    }
}
