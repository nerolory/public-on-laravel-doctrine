<?php

declare(strict_types=1);

namespace App\DTO\Article;

/**
 * Число статей на сайте для главной страницы админки.
 */
final readonly class ArticleCountsData
{
    /**
     * @param int $total       всего статей, кроме архивных
     * @param int $unpublished не опубликованных: на модерации и отклонённых
     */
    public function __construct(
        public int $total,
        public int $unpublished,
    ) {
    }
}
