<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Итог статистики за период: подпись и отформатированное значение.
 */
final readonly class AdminStatisticTotalData
{
    /**
     * @param string $label подпись показателя
     * @param string $value значение с разделителями разрядов
     */
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
