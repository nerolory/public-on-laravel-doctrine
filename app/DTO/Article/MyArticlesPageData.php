<?php

declare(strict_types=1);

namespace App\DTO\Article;

use App\DTO\Common\PageData;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Страница статей автора и число статей по состояниям.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class MyArticlesPageData implements Arrayable
{
    /**
     * @param PageData<MyArticleData> $page   статьи страницы
     * @param ArticleStatusCountsData $counts число статей всего и по состояниям
     */
    public function __construct(
        public PageData $page,
        public ArticleStatusCountsData $counts,
    ) {
    }

    /**
     * Страница для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->page->toArray() + ['counts' => $this->counts->toArray()];
    }
}
