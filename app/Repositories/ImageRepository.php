<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Image;
use App\Entities\User;
use App\Traits\FindsByUuids;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Загруженные картинки.
 */
final class ImageRepository
{
    use FindsByUuids;

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Картинка по UUID.
     */
    public function findByUuid(string $uuid): ?Image
    {
        return $this->entityManager->getRepository(Image::class)->findOneBy(['uuid' => $uuid]);
    }

    /**
     * Картинки по UUID; неизвестные UUID пропускаются.
     *
     * @param Collection<int, string> $uuids
     *
     * @return Collection<string, Image> картинки по UUID
     */
    public function findByUuids(Collection $uuids): Collection
    {
        return $this->findByUuidsOf(Image::class, $uuids);
    }

    /**
     * Все картинки пользователя в порядке загрузки.
     *
     * @return Collection<int, Image>
     */
    public function ownedBy(User $user): Collection
    {
        return collect($this->entityManager->getRepository(Image::class)->findBy(['owner' => $user], ['id' => 'ASC']));
    }

    /**
     * Сохраняет картинку.
     */
    public function save(Image $image): void
    {
        $this->entityManager->persist($image);
        $this->entityManager->flush();
    }

    /**
     * Удаляет запись о картинке.
     */
    public function delete(Image $image): void
    {
        $this->entityManager->remove($image);
        $this->entityManager->flush();
    }
}
