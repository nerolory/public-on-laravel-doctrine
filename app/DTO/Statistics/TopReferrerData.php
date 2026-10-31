<?php

declare(strict_types=1);

namespace App\DTO\Statistics;

/**
 * Сайт-источник переходов на статьи за период.
 */
final readonly class TopReferrerData
{
    /**
     * @param string $host  домен источника
     * @param int    $views просмотры с переходом с него
     */
    public function __construct(
        public string $host,
        public int $views,
    ) {
    }
}
