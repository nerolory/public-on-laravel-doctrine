<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Строка топа статистики: подпись, адрес публичной страницы и число просмотров.
 */
final readonly class AdminRankRowData
{
    /**
     * @param string      $label подпись строки
     * @param string|null $url   адрес публичной страницы или null, если ссылки нет
     * @param int         $count число просмотров
     */
    public function __construct(
        public string $label,
        public ?string $url,
        public int $count,
    ) {
    }
}
