<?php

declare(strict_types=1);

namespace App\DTO\Statistics;

/**
 * Просмотры статей за день.
 */
final readonly class DailyViewsData
{
    /**
     * @param int $views    просмотры
     * @param int $visitors уникальные посетители
     */
    public function __construct(
        public int $views,
        public int $visitors,
    ) {
    }
}
