<?php

declare(strict_types=1);

namespace App\DTO\Article;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Число статей автора всего и по состояниям.
 *
 * @implements Arrayable<string, int>
 */
final readonly class ArticleStatusCountsData implements Arrayable
{
    /**
     * @param int $all       всего статей, кроме архивных
     * @param int $published опубликовано
     * @param int $rejected  отклонено модератором
     * @param int $pending   ждут модерации
     */
    public function __construct(
        public int $all,
        public int $published,
        public int $rejected,
        public int $pending,
    ) {
    }

    /**
     * Счётчики для ответа API.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'all' => $this->all,
            'published' => $this->published,
            'rejected' => $this->rejected,
            'pending' => $this->pending,
        ];
    }
}
