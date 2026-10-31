<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Ссылка-вкладка или кнопка периода: подпись, адрес и признак открытого раздела.
 */
final readonly class AdminLinkData
{
    /**
     * @param string $label  подпись ссылки
     * @param string $url    адрес страницы админки
     * @param bool   $active открыт ли этот раздел
     */
    public function __construct(
        public string $label,
        public string $url,
        public bool $active = false,
    ) {
    }
}
