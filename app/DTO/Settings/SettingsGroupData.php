<?php

declare(strict_types=1);

namespace App\DTO\Settings;

use App\Enums\Settings\SettingGroup;
use Illuminate\Support\Collection;

/**
 * Значения одной группы настроек из формы админки.
 */
final readonly class SettingsGroupData
{
    /**
     * @param SettingGroup                      $group  группа настроек
     * @param Collection<int, SettingValueData> $values значения полей группы
     */
    public function __construct(
        public SettingGroup $group,
        public Collection $values,
    ) {
    }
}
