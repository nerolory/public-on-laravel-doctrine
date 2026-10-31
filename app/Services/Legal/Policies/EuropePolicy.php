<?php

declare(strict_types=1);

namespace App\Services\Legal\Policies;

/**
 * ЕС: GDPR — opt-in для рассылок и cookies, право на переносимость данных (ст. 20):
 * выгрузка в машиночитаемом виде.
 */
final class EuropePolicy extends RegionPolicy
{
    /** Ключ региона. */
    public const string REGION = 'eu';

    /** По GDPR согласие ребёнка действительно с 16 лет; страны могут снижать порог до 13. */
    private const int MINIMUM_AGE = 16;

    /**
     * Возраст самостоятельного согласия.
     */
    public function minimumAge(): int
    {
        return self::MINIMUM_AGE;
    }

    /**
     * Выгрузка данных обязательна.
     */
    public function allowsDataExport(): bool
    {
        return true;
    }
}
