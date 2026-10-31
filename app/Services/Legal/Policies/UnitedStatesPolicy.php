<?php

declare(strict_types=1);

namespace App\Services\Legal\Policies;

use App\Enums\Legal\ConsentType;
use Illuminate\Support\Collection;

/**
 * США: CCPA/CPRA даёт право узнать, какие данные собраны (выгрузка).
 * Аналитика работает по модели opt-out, баннер cookies не обязателен.
 */
final class UnitedStatesPolicy extends RegionPolicy
{
    /** Ключ региона. */
    public const string REGION = 'us';

    /** COPPA запрещает собирать данные детей младше 13 лет без согласия родителей. */
    private const int MINIMUM_AGE = 13;

    /**
     * Только рассылка: cookies работают без согласия.
     *
     * @return Collection<int, ConsentType>
     */
    public function optionalConsents(): Collection
    {
        return collect([ConsentType::Mailing]);
    }

    /**
     * Возраст самостоятельного согласия.
     */
    public function minimumAge(): int
    {
        return self::MINIMUM_AGE;
    }

    /**
     * Баннер cookies не нужен.
     */
    public function requiresCookieConsent(): bool
    {
        return false;
    }

    /**
     * Выгрузка данных обязательна.
     */
    public function allowsDataExport(): bool
    {
        return true;
    }
}
