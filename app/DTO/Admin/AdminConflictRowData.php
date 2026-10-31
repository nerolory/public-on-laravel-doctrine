<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Строка рейтинга конфликтности: статья или комментарий с оценками.
 */
final readonly class AdminConflictRowData
{
    /**
     * @param string      $articleTitle заголовок статьи
     * @param string      $articleUrl   публичный адрес статьи
     * @param string|null $excerpt      начало текста комментария или null для статьи
     * @param int         $positive     число положительных реакций
     * @param int         $negative     число отрицательных реакций
     * @param float       $conflict     мера конфликтности
     */
    public function __construct(
        public string $articleTitle,
        public string $articleUrl,
        public ?string $excerpt,
        public int $positive,
        public int $negative,
        public float $conflict,
    ) {
    }
}
