<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\DTO\Media\ChartViewData;
use App\DTO\Media\ImageData;
use App\DTO\Poll\PollViewData;
use App\DTO\Reaction\ReactionSummaryData;
use App\DTO\Seo\HeadData;
use App\DTO\Seo\ShareLinkData;
use App\DTO\User\UserCardData;
use App\Enums\Article\ArticleStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Страница статьи: текст, автор, реакции, опрос и SEO.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ArticleViewData implements Arrayable
{
    /**
     * @param int                               $id              идентификатор статьи
     * @param string                            $title           заголовок
     * @param string                            $content         HTML для показа
     * @param ImageData|null                    $cover           обложка
     * @param string                            $excerpt         анонс
     * @param Collection<int, string>           $sections        разделы по алфавиту
     * @param Collection<int, string>           $tags            теги по алфавиту
     * @param ArticleStatus                     $status          состояние модерации
     * @param string|null                       $rejectionReason причина отклонения
     * @param DateTimeImmutable|null            $publishedAt     дата первой публикации
     * @param DateTimeImmutable                 $updatedAt       дата изменения
     * @param int                               $viewCount       число просмотров
     * @param UserCardData|null                 $author          автор; null — удалён
     * @param ArticlePermissionsData            $permissions     доступные действия
     * @param ReactionSummaryData               $reactions       реакции на статью
     * @param HeadData                          $head            заголовок и метатеги
     * @param Collection<int, ShareLinkData>    $share           ссылки «поделиться»
     * @param PollViewData|null                 $poll            опрос статьи
     * @param Collection<string, ChartViewData> $charts          графики по UUID
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public ?ImageData $cover,
        public string $excerpt,
        public Collection $sections,
        public Collection $tags,
        public ArticleStatus $status,
        public ?string $rejectionReason,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public int $viewCount,
        public ?UserCardData $author,
        public ArticlePermissionsData $permissions,
        public ReactionSummaryData $reactions,
        public HeadData $head,
        public Collection $share,
        public ?PollViewData $poll,
        public Collection $charts,
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
            'content' => $this->content,
            'cover' => $this->cover?->toArray(),
            'excerpt' => $this->excerpt,
            'sections' => $this->sections->values()->all(),
            'tags' => $this->tags->values()->all(),
            'status' => $this->status->value,
            'rejection_reason' => $this->rejectionReason,
            'published_at' => $this->publishedAt?->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'view_count' => $this->viewCount,
            'author' => $this->author?->reference(),
            'permissions' => $this->permissions->toArray(),
            'reactions' => $this->reactions->toArray(),
            'head' => $this->head->toArray(),
            'share' => $this->share->toArray(),
            'poll' => $this->poll?->toArray(),
            // Объект по UUID графика; пустой набор остаётся объектом JSON.
            'charts' => (object) $this->charts
                ->map(static fn (ChartViewData $chart): array => $chart->toArray())
                ->all(),
        ];
    }
}
