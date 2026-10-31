<?php

declare(strict_types=1);

namespace App\DTO\Localization;

use App\Entities\Language;

/**
 * Включённый язык сайта: код в адресе, название и папка переводов.
 */
final readonly class LocaleData
{
    /**
     * @param string $code код языка в адресе и заголовке X-Locale
     * @param string $name название в переключателе
     * @param string $file папка переводов в lang/
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $file,
    ) {
    }

    /**
     * Язык из сущности.
     */
    public static function fromEntity(Language $language): self
    {
        return new self($language->getCode(), $language->getName(), $language->getFile());
    }
}
