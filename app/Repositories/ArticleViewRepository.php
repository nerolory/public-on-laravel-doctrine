<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Article;
use App\Entities\ArticleView;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сырые просмотры статей и счётчик просмотров.
 */
final class ArticleViewRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Записывает просмотр и увеличивает счётчик статьи. Вставка идёт через DBAL: повтор за день упирается
     * в уникальный индекс, и такое исключение оставляет EntityManager открытым.
     * Счётчик растёт одним UPDATE, без чтения статьи и без смены её версии.
     *
     * @return bool false — посетитель уже смотрел статью сегодня
     */
    public function record(
        int $articleId,
        ?int $userId,
        string $visitorHash,
        ?string $referrerHost,
        DateTimeImmutable $now,
    ): bool {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->insert('article_views', [
                'article_id' => $articleId,
                'user_id' => $userId,
                'visitor_hash' => $visitorHash,
                'viewed_on' => $now->format('Y-m-d'),
                'referrer_host' => $referrerHost,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }
        $this->entityManager->createQuery(
            sprintf('UPDATE %s a SET a.viewCount = a.viewCount + 1 WHERE a.id = :id', Article::class),
        )
            ->setParameter('id', $articleId)
            ->execute();

        return true;
    }

    /**
     * Удаляет сырые просмотры старше даты; счётчики статей остаются.
     *
     * @return int число удалённых просмотров
     */
    public function prune(DateTimeImmutable $before): int
    {
        return (int) $this->entityManager->createQuery(
            sprintf('DELETE FROM %s v WHERE v.createdAt < :before', ArticleView::class),
        )
            ->setParameter('before', $before)
            ->execute();
    }
}
