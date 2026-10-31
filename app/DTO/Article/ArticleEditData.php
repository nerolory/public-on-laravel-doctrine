<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\DTO\Media\ImageData;
use App\DTO\Poll\PollSettingsData;
use App\Entities\SeoMeta;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Статья для формы правки: поля в виде, который понимает редактор, и версия для блокировки.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ArticleEditData implements Arrayable
{
    /**
     * @param int                     $id              идентификатор статьи
     * @param string                  $title           заголовок
     * @param string                  $content         HTML для редактора
     * @param string|null             $summary         краткое описание
     * @param int                     $version         версия, с которой начали правку
     * @param string|null             $rejectionReason причина отклонения
     * @param SeoMeta                 $seo             SEO-поля
     * @param Collection<int, int>    $sectionIds      разделы статьи
     * @param ImageData|null          $cover           обложка
     * @param Collection<int, string> $tags            теги по алфавиту
     * @param PollSettingsData|null   $poll            настройки опроса
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public ?string $summary,
        public int $version,
        public ?string $rejectionReason,
        public SeoMeta $seo,
        public Collection $sectionIds,
        public ?ImageData $cover,
        public Collection $tags,
        public ?PollSettingsData $poll,
    ) {
    }

    /**
     * Форма для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'summary' => $this->summary,
            'version' => $this->version,
            'rejection_reason' => $this->rejectionReason,
            'seo_title' => $this->seo->title(),
            'seo_description' => $this->seo->description(),
            'section_ids' => $this->sectionIds->values()->all(),
            'cover' => $this->cover?->toArray(),
            'tags' => $this->tags->values()->all(),
            'poll' => $this->poll?->toArray(),
        ];
    }
}
