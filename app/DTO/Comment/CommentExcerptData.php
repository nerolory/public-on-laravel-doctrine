<?php

declare(strict_types=1);

namespace App\DTO\Comment;

/**
 * Начало текста комментария и статья, под которой он оставлен.
 */
final readonly class CommentExcerptData
{
    /**
     * @param int    $id           идентификатор комментария
     * @param string $body         начало текста комментария
     * @param int    $articleId    статья комментария
     * @param string $articleTitle заголовок статьи
     */
    public function __construct(
        public int $id,
        public string $body,
        public int $articleId,
        public string $articleTitle,
    ) {
    }
}
