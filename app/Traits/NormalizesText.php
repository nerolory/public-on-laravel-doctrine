<?php

declare(strict_types=1);

namespace App\Traits;

use RuntimeException;

/**
 * Нормализация текста из форм: пустые строки и лишние пробелы.
 */
trait NormalizesText
{
    /** Пробелы, переносы строк и неразрывные пробелы подряд. */
    private const string SPACES = '/[\s\x{00A0}]+/u';

    /**
     * Строка без пробелов по краям; пустая или null — null.
     */
    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Пробелы и переносы подряд заменяются одним пробелом, по краям пробелов нет.
     */
    private static function collapseSpaces(string $value): string
    {
        return trim(self::replacePattern(self::SPACES, ' ', $value));
    }

    /**
     * Замена по регулярному выражению; при ошибке регулярного выражения выбрасывается исключение.
     *
     * @param string|list<string> $pattern регулярное выражение или список выражений
     *
     * @throws RuntimeException регулярное выражение не обработано
     */
    private static function replacePattern(string|array $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject)
            ?? throw new RuntimeException('Pattern replacement failed: ' . preg_last_error_msg());
    }
}
