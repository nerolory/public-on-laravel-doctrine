<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Contracts\RegistrationPolicy;
use App\Enums\Settings\SettingKey;
use App\Services\Legal\Policies\EuropePolicy;
use App\Services\Legal\Policies\RussiaPolicy;
use App\Services\Legal\Policies\UnitedStatesPolicy;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Правила регистрации по региону из настроек сайта.
 */
final class RegistrationPolicies
{
    /** @var Collection<string, RegistrationPolicy> */
    private Collection $policies;

    /**
     * @param SettingsService    $settings     настройки сайта
     * @param RussiaPolicy       $russia       правила РФ
     * @param EuropePolicy       $europe       правила ЕС
     * @param UnitedStatesPolicy $unitedStates правила США
     */
    public function __construct(
        private readonly SettingsService $settings,
        RussiaPolicy $russia,
        EuropePolicy $europe,
        UnitedStatesPolicy $unitedStates,
    ) {
        $this->policies = collect([$russia, $europe, $unitedStates])
            ->keyBy(static fn (RegistrationPolicy $policy): string => $policy->region());
    }

    /**
     * Правила региона из настроек; регион настроек проверен при сохранении.
     *
     * @throws LogicException регион настроек не описан стратегией
     */
    public function current(): RegistrationPolicy
    {
        $region = $this->settings->string(SettingKey::LegalRegion);

        return $this->policies->get($region) ?? throw new LogicException('Unknown legal region: ' . $region);
    }

    /**
     * Правила всех регионов по ключу региона.
     *
     * @return Collection<string, RegistrationPolicy>
     */
    public function all(): Collection
    {
        return $this->policies;
    }

    /**
     * Ключи всех регионов.
     *
     * @return Collection<int, string>
     */
    public function regions(): Collection
    {
        return $this->policies->keys();
    }
}
