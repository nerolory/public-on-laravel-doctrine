<?php

declare(strict_types=1);

namespace App\DTO\Article;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Статья в ленте и на странице автора: заголовок, анонс и разделы.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ArticleListItemData implements Arrayable
{
    /**
     * @param int                     $id          идентификатор статьи
     * @param string                  $title       заголовок
     * @param string                  $excerpt     анонс
     * @param bool                    $isPublished опубликована ли статья
     * @param Collection<int, string> $sections    разделы по алфавиту
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $excerpt,
        public bool $isPublished,
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
            'is_published' => $this->isPublished,
            'excerpt' => $this->excerpt,
            'sections' => $this->sections->values()->all(),
        ];
    }
}
