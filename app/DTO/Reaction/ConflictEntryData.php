<?php

declare(strict_types=1);

namespace App\DTO\Reaction;

/**
 * Строка рейтинга конфликтности: оценка и статья.
 */
final readonly class ConflictEntryData
{
    /**
     * @param ConflictScoreData $score        оценка конфликтности
     * @param int               $articleId    статья или статья комментария
     * @param string            $articleTitle заголовок статьи
     * @param string|null       $excerpt      начало текста комментария
     */
    public function __construct(
        public ConflictScoreData $score,
        public int $articleId,
        public string $articleTitle,
        public ?string $excerpt = null,
    ) {
    }
}
