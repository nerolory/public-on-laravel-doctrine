<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\Legal\ConsentType;
use App\Services\Legal\RegistrationPolicies;
use Illuminate\Support\Collection;

/**
 * Правила регистрации и работы с согласиями для правового региона (стратегия).
 * Новый регион добавляется отдельным классом и регистрируется в {@see RegistrationPolicies}.
 */
interface RegistrationPolicy
{
    /**
     * Ключ региона в настройках и в документах: ru, eu, us.
     */
    public function region(): string;

    /**
     * Согласия, без которых нельзя зарегистрироваться и публиковать.
     *
     * @return Collection<int, ConsentType>
     */
    public function requiredConsents(): Collection;

    /**
     * Добровольные согласия: рассылки и cookies.
     *
     * @return Collection<int, ConsentType>
     */
    public function optionalConsents(): Collection;

    /**
     * Возраст, с которого человек сам даёт согласие на обработку данных.
     */
    public function minimumAge(): int;

    /**
     * Нужен ли баннер согласия на cookies до загрузки аналитики.
     */
    public function requiresCookieConsent(): bool;

    /**
     * Может ли пользователь выгрузить свои данные.
     */
    public function allowsDataExport(): bool;
}
