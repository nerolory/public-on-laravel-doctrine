<?php

declare(strict_types=1);

namespace App\DTO\User;

/**
 * Открытая ссылка автора на его страницу в соцсети.
 */
final readonly class SocialLinkData
{
    /**
     * @param string $network ключ соцсети: vk, telegram, github и т. п.
     * @param string $url     адрес страницы
     */
    public function __construct(
        public string $network,
        public string $url,
    ) {
    }
}
