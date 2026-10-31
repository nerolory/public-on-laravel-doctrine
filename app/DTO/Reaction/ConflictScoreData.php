<?php

declare(strict_types=1);

namespace App\DTO\Reaction;

/**
 * Оценка конфликтности статьи или комментария.
 */
final readonly class ConflictScoreData
{
    /**
     * @param int   $id       идентификатор статьи или комментария
     * @param int   $positive число положительных реакций
     * @param int   $negative число отрицательных реакций
     * @param float $conflict мера конфликтности: pos·neg/(pos+neg)
     */
    public function __construct(
        public int $id,
        public int $positive,
        public int $negative,
        public float $conflict,
    ) {
    }
}
