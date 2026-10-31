<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Collection;

/**
 * Поиск записей по UUID одним запросом. Подключается в репозиториях, у которых есть поле entityManager.
 */
trait FindsByUuids
{
    /**
     * Записи класса по UUID; неизвестные UUID пропускаются.
     *
     * @template TEntity of object
     *
     * @param class-string<TEntity>   $class сущность с полем uuid
     * @param Collection<int, string> $uuids
     *
     * @return Collection<string, TEntity> по UUID
     */
    private function findByUuidsOf(string $class, Collection $uuids): Collection
    {
        if ($uuids->isEmpty()) {
            return collect();
        }

        $entities = $this->entityManager->getRepository($class)
            ->findBy(['uuid' => $uuids->unique()->values()->all()]);

        return collect($entities)
            ->keyBy(static fn (object $entity): string => $entity->getUuid());
    }
}
