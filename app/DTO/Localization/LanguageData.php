<?php

declare(strict_types=1);

namespace App\DTO\Localization;

/**
 * Данные языка из формы в админке.
 */
final readonly class LanguageData
{
    /**
     * @param string $code      код языка
     * @param string $name      название в переключателе
     * @param string $file      папка переводов в lang/
     * @param bool   $enabled   включён ли язык
     * @param bool   $isDefault сделать языком по умолчанию
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $file,
        public bool $enabled,
        public bool $isDefault,
    ) {
    }
}
