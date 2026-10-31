<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\Entities\Article;
use App\Entities\ArticleView;
use App\Entities\User;
use App\Enums\Settings\SettingKey;
use App\Repositories\ArticleViewRepository;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

/**
 * Учёт просмотров: без ботов, без самого автора, один раз в сутки на посетителя.
 */
final class ArticleViewService
{
    /**
     * @param ArticleViewRepository $views    просмотры
     * @param CrawlerDetect         $crawlers распознавание ботов
     * @param SettingsService       $settings срок хранения просмотров
     * @param string                $secret   ключ приложения для хеша посетителя
     */
    public function __construct(
        private readonly ArticleViewRepository $views,
        private readonly CrawlerDetect $crawlers,
        private readonly SettingsService $settings,
        private readonly string $secret,
    ) {
    }

    /**
     * Записывает просмотр опубликованной статьи.
     *
     * @return bool false — просмотр не учитывается: нет IP или User-Agent, бот, автор, неопубликованная статья
     *              или повтор за день
     */
    public function record(Article $article, ?User $viewer, ?string $ip, ?string $userAgent, ?string $referrer): bool
    {
        // Без IP или User-Agent посетителя нельзя отличить от других, поэтому просмотр не учитывается.
        if ($ip === null || $ip === '' || $userAgent === null || $userAgent === '') {
            return false;
        }

        if ($this->crawlers->isCrawler($userAgent)) {
            return false;
        }

        if (! $article->isPublished() || ($viewer !== null && $article->isOwnedBy($viewer))) {
            return false;
        }

        // Секрет приложения в хеше: по базе нельзя восстановить IP перебором.
        $visitor = hash_hmac('sha256', $ip . "\n" . $userAgent, $this->secret);
        $host = $referrer === null ? null : parse_url($referrer, PHP_URL_HOST);

        return $this->views->record(
            articleId: $article->persistedId(),
            userId: $viewer === null ? null : (int) $viewer->getAuthIdentifier(),
            visitorHash: $visitor,
            referrerHost: is_string($host) ? mb_substr($host, 0, ArticleView::REFERRER_LENGTH) : null,
            now: CarbonImmutable::now(),
        );
    }

    /**
     * Удаляет просмотры старше срока хранения из настроек.
     *
     * @return int число удалённых просмотров
     */
    public function prune(): int
    {
        $months = $this->settings->integer(SettingKey::StatisticsViewsRetentionMonths);

        return $this->views->prune(CarbonImmutable::now()->subMonths($months));
    }
}
