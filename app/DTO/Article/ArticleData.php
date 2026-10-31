<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\DTO\Poll\PollData;
use App\Entities\SeoMeta;
use Illuminate\Support\Collection;

/**
 * Данные статьи из формы автора или модератора.
 */
final readonly class ArticleData
{
    /**
     * @param string                  $title        заголовок
     * @param string                  $content      HTML текста из редактора
     * @param string|null             $summary      краткое описание
     * @param Collection<int, int>    $sectionIds   разделы статьи
     * @param Collection<int, string> $tags         теги
     * @param string|null             $coverId      UUID загруженной обложки
     * @param bool                    $pollProvided опрос в запросе; без него не меняется
     * @param PollData|null           $poll         параметры опроса; null убирает опрос
     * @param SeoMeta|null            $seo          SEO-поля; меняет только модератор
     */
    public function __construct(
        public string $title,
        public string $content,
        public ?string $summary,
        public Collection $sectionIds,
        public Collection $tags,
        public ?string $coverId = null,
        public bool $pollProvided = false,
        public ?PollData $poll = null,
        public ?SeoMeta $seo = null,
    ) {
    }
}
