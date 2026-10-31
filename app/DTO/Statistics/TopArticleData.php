<?php

declare(strict_types=1);

namespace App\DTO\Statistics;

/**
 * Статья в топе просмотров за период.
 */
final readonly class TopArticleData
{
    /**
     * @param int    $id    идентификатор статьи
     * @param string $title заголовок
     * @param int    $views просмотры за период
     */
    public function __construct(
        public int $id,
        public string $title,
        public int $views,
    ) {
    }
}
