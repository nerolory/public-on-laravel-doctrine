<?php

declare(strict_types=1);

namespace App\DTO\Admin;

/**
 * Автор в топе по числу статей.
 */
final readonly class TopAuthorData
{
    /**
     * @param string $name         публичное имя или почта автора
     * @param int    $articleCount число статей
     */
    public function __construct(
        public string $name,
        public int $articleCount,
    ) {
    }
}
