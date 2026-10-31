<?php

declare(strict_types=1);

namespace App\DTO\Seo;

/**
 * Языковая версия страницы для hreflang.
 */
final readonly class AlternateUrlData
{
    /**
     * @param string $hreflang код языка или x-default
     * @param string $href     полный адрес версии
     */
    public function __construct(
        public string $hreflang,
        public string $href,
    ) {
    }
}
