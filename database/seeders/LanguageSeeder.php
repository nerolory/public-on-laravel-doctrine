<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTO\Localization\LanguageData;
use App\Services\Localization\LanguageService;
use Illuminate\Database\Seeder;

/**
 * Языки по умолчанию: русский основной, английский включён. Существующие языки не меняются.
 */
final class LanguageSeeder extends Seeder
{
    /**
     * Создаёт языки по умолчанию, которых ещё нет в базе.
     */
    public function run(LanguageService $languages): void
    {
        $defaults = [
            new LanguageData(code: 'ru', name: 'Русский', file: 'ru', enabled: true, isDefault: true),
            new LanguageData(code: 'en', name: 'English', file: 'en', enabled: true, isDefault: false),
        ];

        foreach ($defaults as $language) {
            if (! $languages->exists($language->code)) {
                $languages->create($language);
            }
        }
    }
}
