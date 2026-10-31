<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ModerationStore;
use App\DTO\Admin\TopAuthorData;
use App\DTO\Article\ArticleCountsData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\PersonName;
use App\Entities\User;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Статьи: поиск, сохранение и выборки для админки, карты сайта и RSS.
 */
final class ArticleRepository implements ModerationStore
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Автор статьи, если его учётная запись не удалена. Запрос срабатывает с фильтром активных пользователей.
     */
    public function activeAuthor(Article $article): ?User
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $article->getUser()->getAuthIdentifier())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Статья по идентификатору.
     */
    public function findEntity(int $id): ?Article
    {
        return $this->entityManager->find(Article::class, $id);
    }

    /**
     * Статьи по идентификаторам.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Article>
     */
    public function findByIds(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return collect($this->entityManager->getRepository(Article::class)->findBy(['id' => $ids->all()]));
    }

    /**
     * Оптимистическая блокировка: если статью изменили после открытия формы, Doctrine бросит OptimisticLockException.
     */
    public function lockVersion(Article $article, int $version): void
    {
        $this->entityManager->lock($article, LockMode::OPTIMISTIC, $version);
    }

    /**
     * Число статей и неопубликованных среди них.
     */
    public function counts(): ArticleCountsData
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id) AS total')
            ->addSelect('SUM(CASE WHEN a.isPublished = false THEN 1 ELSE 0 END) AS unpublished')
            ->from(Article::class, 'a')
            ->getQuery()
            ->getSingleResult();

        return new ArticleCountsData(total: (int) $row['total'], unpublished: (int) $row['unpublished']);
    }

    /**
     * Авторы с наибольшим числом статей; удалённые учётные записи не учитываются.
     *
     * @return Collection<int, TopAuthorData>
     */
    public function topAuthors(int $limit): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.email, u.fullName.firstName AS firstName, u.fullName.middleName AS middleName')
            ->addSelect('u.fullName.lastName AS lastName, COUNT(a.id) AS articleCount')
            ->from(Article::class, 'a')
            ->join('a.user', 'u')
            ->where('u.deletedAt IS NULL')
            ->groupBy('u.id, u.email, u.fullName.firstName, u.fullName.middleName, u.fullName.lastName')
            ->orderBy('articleCount', 'DESC')
            ->addOrderBy('u.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->map(static fn (array $row): TopAuthorData => new TopAuthorData(
            name: PersonName::displayOrEmail($row['firstName'], $row['middleName'], $row['lastName'], $row['email']),
            articleCount: (int) $row['articleCount'],
        ));
    }

    /**
     * Добавляет раздел статьям с указанными заголовками, у которых ещё нет ни одного раздела.
     *
     * @param Collection<int, string> $titles
     *
     * @return int число статей, получивших раздел
     */
    public function assignSectionByTitles(ArticleSection $section, Collection $titles): int
    {
        if ($titles->isEmpty()) {
            return 0;
        }

        $articles = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->where('a.title IN (:titles)')
            ->andWhere('a.sections IS EMPTY')
            ->setParameter('titles', $titles->all())
            ->getQuery()
            ->getResult();

        foreach ($articles as $article) {
            $article->addSection($section);
        }

        $this->entityManager->flush();

        return count($articles);
    }

    /**
     * Опубликованные статьи для карты сайта.
     *
     * @return Collection<int, DateTimeImmutable> дата изменения по идентификатору статьи
     */
    public function publishedForSitemap(): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.id, a.updatedAt')
            ->from(Article::class, 'a')
            ->where('a.isPublished = true')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->pluck('updatedAt', 'id');
    }

    /**
     * Последние опубликованные статьи с авторами для RSS.
     *
     * @return Collection<int, Article>
     */
    public function latestPublished(int $limit): Collection
    {
        return collect($this->entityManager->createQueryBuilder()
            ->select('a, u')
            ->from(Article::class, 'a')
            ->join('a.user', 'u')
            ->where('a.isPublished = true')
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult());
    }

    /**
     * Заголовки статей по идентификаторам.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, string> заголовки по идентификатору статьи
     */
    public function titles(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.id, a.title')
            ->from(Article::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->pluck('title', 'id');
    }

    /**
     * Сохраняет статью.
     */
    public function save(Article $article): void
    {
        $this->entityManager->persist($article);
        $this->entityManager->flush();
    }

    /**
     * Сохраняет изменения загруженных статей.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Удаляет статьи модератором без причины.
     *
     * @param iterable<Article> $articles
     */
    public function delete(iterable $articles): void
    {
        foreach ($articles as $article) {
            $this->entityManager->remove($article);
        }

        $this->entityManager->flush();
    }

    /**
     * Сначала сохраняет причину, затем удаляет: SoftDeleteable не пересчитывает изменения у сущности,
     * уже поставленной в очередь на удаление, и причина в том же flush потерялась бы.
     */
    public function archive(Article $article, string $reason): void
    {
        $this->entityManager->wrapInTransaction(function () use ($article, $reason): void {
            $article->markForArchive($reason);
            $this->entityManager->flush();
            $this->entityManager->remove($article);
            $this->entityManager->flush();
        });
    }
}
