<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\Enums\Article\ArticleStatus;
use App\Enums\Moderation\ModerationAction;
use Illuminate\Support\Collection;

/**
 * Строка списка модерации статей в админке.
 */
final readonly class ArticleModerationRowData
{
    /**
     * @param int                     $id              идентификатор статьи
     * @param string                  $title           заголовок
     * @param string                  $excerpt         анонс
     * @param ArticleStatus           $status          состояние модерации
     * @param string|null             $rejectionReason причина отклонения
     * @param int                     $viewCount       число просмотров
     * @param string|null             $author          имя автора; null — удалён
     * @param Collection<int, string> $sections        разделы по алфавиту
     * @param ModerationAction        $currentAction   действие, ведущее к статусу
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $excerpt,
        public ArticleStatus $status,
        public ?string $rejectionReason,
        public int $viewCount,
        public ?string $author,
        public Collection $sections,
        public ModerationAction $currentAction,
    ) {
    }
}
