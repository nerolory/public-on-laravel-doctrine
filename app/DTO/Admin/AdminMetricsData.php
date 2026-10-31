<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use App\DTO\Article\ArticleCountsData;
use App\DTO\User\UserCountsData;
use Illuminate\Support\Collection;

/**
 * Показатели главной страницы админки.
 */
final readonly class AdminMetricsData
{
    /**
     * @param ArticleCountsData              $articles   статьи: всего и неопубликованные
     * @param UserCountsData                 $users      пользователи, заблокированные
     * @param Collection<int, TopAuthorData> $topAuthors топ авторов по числу статей
     */
    public function __construct(
        public ArticleCountsData $articles,
        public UserCountsData $users,
        public Collection $topAuthors,
    ) {
    }
}
