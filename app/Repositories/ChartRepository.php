<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Chart;
use App\Traits\FindsByUuids;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Графики статей.
 */
final class ChartRepository
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
     * Графики по UUID; неизвестные UUID пропускаются.
     *
     * @param Collection<int, string> $uuids
     *
     * @return Collection<string, Chart> графики по UUID
     */
    public function findByUuids(Collection $uuids): Collection
    {
        return $this->findByUuidsOf(Chart::class, $uuids);
    }

    /**
     * Сохраняет график.
     */
    public function save(Chart $chart): void
    {
        $this->entityManager->persist($chart);
        $this->entityManager->flush();
    }
}
