<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\UserRepository;
use App\DTO\Admin\AdminMetricsData;
use App\Repositories\ArticleRepository;
use App\Support\Settings\PageSizes;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Показатели главной страницы админки.
 */
final class AdminMetricsService
{
    /** Ключ кеша показателей. */
    private const string CACHE_KEY = 'admin.metrics';

    /** Время жизни показателей в кеше в секундах: главная админки открывается часто, точность до минуты достаточна. */
    private const int CACHE_TTL = 60;

    /**
     * @param ArticleRepository $articles  статьи
     * @param UserRepository    $users     пользователи
     * @param Cache             $cache     кеш показателей
     * @param PageSizes         $pageSizes длина топа авторов
     */
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly UserRepository $users,
        private readonly Cache $cache,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Число статей и пользователей, неопубликованные статьи, заблокированные и топ авторов.
     */
    public function get(): AdminMetricsData
    {
        return $this->cache->remember(self::CACHE_KEY, self::CACHE_TTL, function (): AdminMetricsData {
            return new AdminMetricsData(
                articles: $this->articles->counts(),
                users: $this->users->counts(),
                topAuthors: $this->articles->topAuthors($this->pageSizes->topAuthors()),
            );
        });
    }
}
