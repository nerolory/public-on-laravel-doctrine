<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ModerationStore;
use App\DTO\Album\AlbumModerationRowData;
use App\DTO\Album\AlbumTitleData;
use App\DTO\Common\PageData;
use App\Entities\Album;
use App\Entities\PersonName;
use App\Entities\User;
use App\Enums\Moderation\ModerationStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Альбомы: выборки для сайта, «Моих альбомов», модерации, жалоб и карты сайта.
 */
final class AlbumRepository implements ModerationStore
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Альбом с фото и картинками одним запросом.
     */
    public function findByUuid(string $uuid): ?Album
    {
        return $this->withPhotos()
            ->where('a.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Альбом по идентификатору.
     */
    public function findById(int $id): ?Album
    {
        return $this->entityManager->find(Album::class, $id);
    }

    /**
     * Альбомы с фото по UUID; неизвестные UUID пропускаются.
     *
     * @param Collection<int, string> $uuids
     *
     * @return Collection<string, Album> альбомы по UUID
     */
    public function findByUuids(Collection $uuids): Collection
    {
        if ($uuids->isEmpty()) {
            return collect();
        }

        $albums = $this->withPhotos()
            ->where('a.uuid IN (:uuids)')
            ->setParameter('uuids', $uuids->all())
            ->getQuery()
            ->getResult();

        return collect($albums)->keyBy(static fn (Album $album): string => $album->getUuid());
    }

    /**
     * Опубликованные альбомы для карты сайта.
     *
     * @return Collection<string, DateTimeImmutable> время последней правки по UUID
     */
    public function publishedForSitemap(): Collection
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.uuid, a.updatedAt')
            ->from(Album::class, 'a')
            ->where('a.status = :published')
            ->setParameter('published', ModerationStatus::Published)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->pluck('updatedAt', 'uuid');
    }

    /**
     * Названия альбомов для очереди жалоб.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, AlbumTitleData> названия по идентификатору альбома
     */
    public function titles(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.id, a.uuid, a.title')
            ->from(Album::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->getQuery()
            ->getArrayResult();

        return collect($rows)->mapWithKeys(static fn (array $row): array => [
            (int) $row['id'] => new AlbumTitleData((int) $row['id'], $row['uuid'], $row['title']),
        ]);
    }

    /**
     * Альбомы владельца: недавно изменённые первыми.
     *
     * @return PageData<Album>
     */
    public function ownedBy(User $owner, int $page, int $limit): PageData
    {
        $ids = $this->entityManager->createQueryBuilder()
            ->select('a.id')
            ->from(Album::class, 'a')
            ->where('a.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('a.updatedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            // Лишняя запись показывает, есть ли следующая страница.
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getSingleColumnResult();
        $ids = collect($ids)->map(static fn (mixed $id): int => (int) $id);
        $page = PageData::fromOverfetched($ids, $limit);

        return new PageData($this->withItems($page->items), hasMore: $page->hasMore);
    }

    /**
     * Очередь модерации: свежие сверху, с владельцем и числом фото.
     *
     * @return PageData<AlbumModerationRowData>
     */
    public function moderationPage(int $page, int $limit): PageData
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.uuid, a.title, a.status, a.rejectionReason, COUNT(i.id) AS photos, u.email')
            ->addSelect('u.fullName.firstName AS firstName, u.fullName.middleName AS middleName')
            ->addSelect('u.fullName.lastName AS lastName')
            ->from(Album::class, 'a')
            ->leftJoin('a.items', 'i')
            ->leftJoin('a.owner', 'u')
            ->groupBy('a.id, a.uuid, a.title, a.status, a.rejectionReason, u.id, u.email')
            ->addGroupBy('u.fullName.firstName, u.fullName.middleName, u.fullName.lastName')
            ->orderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
        $total = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Album::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        return new PageData(collect($rows)->map(self::moderationRow(...)), total: $total);
    }

    /**
     * Альбомы для действия модератора, без фото.
     *
     * @param Collection<int, string> $uuids
     *
     * @return Collection<int, Album>
     */
    public function findForModeration(Collection $uuids): Collection
    {
        if ($uuids->isEmpty()) {
            return collect();
        }

        return collect($this->entityManager->getRepository(Album::class)->findBy(['uuid' => $uuids->all()]));
    }

    /**
     * Сохраняет альбом.
     */
    public function save(Album $album): void
    {
        $this->entityManager->persist($album);
        $this->entityManager->flush();
    }

    /**
     * Записывает изменения загруженных альбомов.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * Удаляет альбомы вместе с фото.
     *
     * @param iterable<Album> $albums
     */
    public function delete(iterable $albums): void
    {
        foreach ($albums as $album) {
            $this->entityManager->remove($album);
        }

        $this->entityManager->flush();
    }

    /**
     * Выборка альбомов вместе с фото и картинками.
     */
    private function withPhotos(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a, i, img')
            ->from(Album::class, 'a')
            ->leftJoin('a.items', 'i')
            ->leftJoin('i.image', 'img');
    }

    /**
     * Альбомы по id в заданном порядке, с фото. Отдельным запросом: LIMIT по объединению
     * с коллекцией считал бы строки фото.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, Album>
     */
    private function withItems(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $albums = collect($this->withPhotos()
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids->all())
            ->getQuery()
            ->getResult())
            ->keyBy(static fn (Album $album): int => $album->persistedId());

        return $ids->map(static fn (int $id): ?Album => $albums->get($id))->filter()->values();
    }

    /**
     * Строка очереди модерации из результата запроса.
     *
     * @param array<string, mixed> $row
     */
    private static function moderationRow(array $row): AlbumModerationRowData
    {
        $owner = $row['email'] === null
            ? null
            : PersonName::displayOrEmail($row['firstName'], $row['middleName'], $row['lastName'], $row['email']);

        return new AlbumModerationRowData(
            uuid: $row['uuid'],
            title: $row['title'],
            status: $row['status'],
            rejectionReason: $row['rejectionReason'],
            photos: (int) $row['photos'],
            owner: $owner,
        );
    }
}
