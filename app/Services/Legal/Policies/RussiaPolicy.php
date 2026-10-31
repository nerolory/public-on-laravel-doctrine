<?php

declare(strict_types=1);

namespace App\Services\Legal\Policies;

/**
 * РФ: 152-ФЗ требует отдельного согласия на обработку ПДн. Рекламная рассылка — только с отдельного
 * согласия (38-ФЗ «О рекламе»). Аналитические cookies относятся к ПДн.
 */
final class RussiaPolicy extends RegionPolicy
{
    /** Ключ региона. */
    public const string REGION = 'ru';

    /** С 14 лет согласие на обработку ПДн дают самостоятельно. */
    private const int MINIMUM_AGE = 14;

    /**
     * Возраст самостоятельного согласия.
     */
    public function minimumAge(): int
    {
        return self::MINIMUM_AGE;
    }

    /**
     * Выгрузка данных законом не требуется.
     */
    public function allowsDataExport(): bool
    {
        return false;
    }
}
