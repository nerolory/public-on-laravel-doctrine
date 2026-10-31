<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Statistics\DailyViewsData;
use App\DTO\Statistics\TopArticleData;
use App\DTO\Statistics\TopReferrerData;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Агрегаты для статистики в админке. Считает база: группировка по дням через DATE().
 * Сырые просмотры уже очищены от ботов при записи.
 */
final class StatisticsRepository
{
    /** Длина даты YYYY-MM-DD в начале значения дня из базы. */
    private const int DATE_LENGTH = 10;

    /** Условие для статей: только опубликованные. */
    private const string PUBLISHED = 'is_published = TRUE';

    /** Условие для комментариев: только не удалённые. */
    private const string NOT_DELETED = 'deleted_at IS NULL';

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Просмотры и уникальные посетители по дням начиная с даты.
     *
     * @return Collection<string, DailyViewsData> по дате YYYY-MM-DD
     */
    public function viewsByDay(string $from): Collection
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT viewed_on AS day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors'
            . ' FROM article_views WHERE viewed_on >= ? GROUP BY viewed_on',
            [$from],
        );

        return collect($rows)->mapWithKeys(static fn (array $row): array => [
            self::day($row['day']) => new DailyViewsData((int) $row['views'], (int) $row['visitors']),
        ]);
    }

    /**
     * Регистрации по дням начиная с даты.
     *
     * @return Collection<string, int> по дате YYYY-MM-DD
     */
    public function registrationsByDay(string $from): Collection
    {
        return $this->countByDay('users', 'created_at', $from);
    }

    /**
     * Опубликованные статьи по дням публикации начиная с даты.
     *
     * @return Collection<string, int> по дате YYYY-MM-DD
     */
    public function publishedArticlesByDay(string $from): Collection
    {
        return $this->countByDay('articles', 'published_at', $from, self::PUBLISHED);
    }

    /**
     * Комментарии, не удалённые, по дням начиная с даты.
     *
     * @return Collection<string, int> по дате YYYY-MM-DD
     */
    public function commentsByDay(string $from): Collection
    {
        return $this->countByDay('comments', 'created_at', $from, self::NOT_DELETED);
    }

    /**
     * Самые просматриваемые статьи начиная с даты.
     *
     * @return Collection<int, TopArticleData>
     */
    public function topArticles(string $from, int $limit): Collection
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT a.id, a.title, COUNT(v.id) AS views FROM article_views v'
            . ' JOIN articles a ON a.id = v.article_id WHERE v.viewed_on >= ?'
            . ' GROUP BY a.id, a.title ORDER BY views DESC, a.id DESC LIMIT ?',
            [$from, $limit],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return collect($rows)->map(static fn (array $row): TopArticleData => new TopArticleData(
            (int) $row['id'],
            (string) $row['title'],
            (int) $row['views'],
        ));
    }

    /**
     * Сайты, с которых чаще всего переходят на статьи, начиная с даты.
     *
     * @return Collection<int, TopReferrerData>
     */
    public function topReferrers(string $from, int $limit): Collection
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT referrer_host AS host, COUNT(*) AS views FROM article_views'
            . ' WHERE viewed_on >= ? AND referrer_host IS NOT NULL'
            . ' GROUP BY referrer_host ORDER BY views DESC LIMIT ?',
            [$from, $limit],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        return collect($rows)->map(static fn (array $row): TopReferrerData => new TopReferrerData(
            (string) $row['host'],
            (int) $row['views'],
        ));
    }

    /**
     * Число записей по дням для таблицы с колонкой времени. Имя таблицы, колонка и условие задаются
     * константами методов выше: пользовательский ввод сюда не попадает. Без условия считаются все записи периода.
     *
     * @return Collection<string, int> по дате YYYY-MM-DD
     */
    private function countByDay(string $table, string $column, string $from, ?string $condition = null): Collection
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            sprintf(
                'SELECT DATE(%2$s) AS day, COUNT(*) AS total FROM %1$s WHERE %2$s >= ?%3$s GROUP BY DATE(%2$s)',
                $table,
                $column,
                $condition === null ? '' : ' AND ' . $condition,
            ),
            [$from . ' 00:00:00'],
        );

        return collect($rows)
            ->mapWithKeys(static fn (array $row): array => [self::day($row['day']) => (int) $row['total']]);
    }

    /**
     * День в виде YYYY-MM-DD: первые десять символов значения, которое вернула база.
     */
    private static function day(mixed $value): string
    {
        return substr((string) $value, 0, self::DATE_LENGTH);
    }
}
