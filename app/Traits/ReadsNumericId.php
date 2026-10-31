<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Числовой идентификатор из адреса или формы: строка из одних цифр, иначе null.
 */
trait ReadsNumericId
{
    /**
     * Идентификатор как целое число; UUID и прочие строки дают null.
     */
    private function numericId(string $id): ?int
    {
        return ctype_digit($id) ? (int) $id : null;
    }
}
