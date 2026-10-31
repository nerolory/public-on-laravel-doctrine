<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Вариант выбора в админке: значение формы, подпись и признак выбранного варианта.
 */
final readonly class AdminOptionData
{
    /**
     * @param string $value    значение, которое уходит в форму
     * @param string $label    подпись варианта
     * @param bool   $selected выбран ли вариант
     */
    public function __construct(
        public string $value,
        public string $label,
        public bool $selected = false,
    ) {
    }
}
