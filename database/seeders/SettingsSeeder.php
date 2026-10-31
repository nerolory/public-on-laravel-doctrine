<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Repositories\SettingRepository;
use App\Services\Settings\SettingsSchema;
use Illuminate\Database\Seeder;

/**
 * Значения настроек по умолчанию из схемы: отсутствующие ключи добавляются, пустые обязательные значения заполняются.
 * Заполненные значения не меняются, повторный запуск безопасен.
 */
final class SettingsSeeder extends Seeder
{
    /**
     * Добавляет отсутствующие настройки и заполняет пустые обязательные.
     */
    public function run(SettingsSchema $schema, SettingRepository $settings): void
    {
        $settings->insertMissing($schema->defaults());
        $settings->fillEmpty($schema->requiredDefaults());
    }
}
