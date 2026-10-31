<?php

declare(strict_types=1);

namespace App\DTO\Article;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Статья в выгрузке данных пользователя.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ArticleExportData implements Arrayable
{
    /**
     * @param int                     $id          идентификатор статьи
     * @param string                  $title       заголовок
     * @param string                  $content     HTML текста
     * @param bool                    $isPublished опубликована ли статья
     * @param bool                    $isRejected  отклонена ли статья
     * @param Collection<int, string> $sections    разделы по алфавиту
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public bool $isPublished,
        public bool $isRejected,
        public Collection $sections,
    ) {
    }

    /**
     * Статья для файла выгрузки.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'is_published' => $this->isPublished,
            'is_rejected' => $this->isRejected,
            'sections' => $this->sections->values()->all(),
        ];
    }
}
