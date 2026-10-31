<?php

declare(strict_types=1);

namespace App\Services\Legal\Policies;

use App\Contracts\RegistrationPolicy;
use App\Enums\Legal\ConsentType;
use Illuminate\Support\Collection;

/**
 * Общая часть правил региона: код региона и обязательные согласия одинаковы. Подкласс задаёт константу REGION
 * и остальные правила.
 */
abstract class RegionPolicy implements RegistrationPolicy
{
    /**
     * Ключ региона из константы REGION подкласса.
     */
    public function region(): string
    {
        return static::REGION;
    }

    /**
     * ПДн, правила сайта и подтверждение возраста.
     *
     * @return Collection<int, ConsentType>
     */
    public function requiredConsents(): Collection
    {
        return collect([ConsentType::PersonalData, ConsentType::SiteRules, ConsentType::Age]);
    }

    /**
     * Рассылка, аналитические и рекламные cookies. Регион с другим набором переопределяет метод.
     *
     * @return Collection<int, ConsentType>
     */
    public function optionalConsents(): Collection
    {
        return collect([ConsentType::Mailing, ConsentType::AnalyticsCookies, ConsentType::MarketingCookies]);
    }

    /**
     * Баннер cookies обязателен. Регион без обязательного баннера переопределяет метод.
     */
    public function requiresCookieConsent(): bool
    {
        return true;
    }
}
