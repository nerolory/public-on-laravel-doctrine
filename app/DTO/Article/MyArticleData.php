<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\Enums\Article\ArticleStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Статья в списке «Мои статьи»: состояние, причина отклонения и просмотры.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class MyArticleData implements Arrayable
{
    /**
     * @param int                     $id              идентификатор статьи
     * @param string                  $title           заголовок
     * @param string                  $excerpt         анонс
     * @param ArticleStatus           $status          состояние модерации
     * @param string|null             $rejectionReason причина отклонения
     * @param int                     $viewCount       число просмотров
     * @param DateTimeImmutable       $updatedAt       дата последнего изменения
     * @param DateTimeImmutable|null  $publishedAt     дата первой публикации
     * @param Collection<int, string> $sections        названия разделов по алфавиту
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $excerpt,
        public ArticleStatus $status,
        public ?string $rejectionReason,
        public int $viewCount,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $publishedAt,
        public Collection $sections,
    ) {
    }

    /**
     * Статья для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejectionReason,
            'view_count' => $this->viewCount,
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'published_at' => $this->publishedAt?->format(DATE_ATOM),
            'sections' => $this->sections->values()->all(),
        ];
    }
}
