<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\LegalDocument;
use App\Enums\Legal\ConsentType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Версии правовых документов.
 */
final class LegalDocumentRepository
{
    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Последние версии документов указанных видов для региона. Документ региона важнее общего (region = null).
     *
     * @param Collection<int, ConsentType> $types
     *
     * @return Collection<string, LegalDocument> по значению ConsentType
     */
    public function current(Collection $types, string $region): Collection
    {
        if ($types->isEmpty()) {
            return collect();
        }

        $documents = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(LegalDocument::class, 'd')
            ->where('d.type IN (:types)')
            ->andWhere('(d.region = :region OR d.region IS NULL)')
            ->setParameter('types', $types->map(static fn (ConsentType $type): string => $type->value)->all())
            ->setParameter('region', $region)
            ->orderBy('d.version', 'ASC')
            ->getQuery()
            ->getResult();

        // Региональная версия вытесняет общую; внутри одного уровня побеждает более новая (сортировка по возрастанию).
        return collect($documents)->reduce(static function (Collection $current, LegalDocument $document): Collection {
            $key = $document->getType()->value;
            $existing = $current->get($key);

            if ($existing === null || $document->getRegion() !== null || $existing->getRegion() === null) {
                $current->put($key, $document);
            }

            return $current;
        }, collect());
    }

    /**
     * Последняя версия каждой пары «вид + регион»: выбирает база, старые версии не загружаются.
     *
     * @return Collection<string, LegalDocument> по ключу «вид|регион»; у общих документов регион пустой
     */
    public function latestPerPair(): Collection
    {
        $documents = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(LegalDocument::class, 'd')
            ->where('d.version = (SELECT MAX(d2.version) FROM ' . LegalDocument::class . ' d2'
                . ' WHERE d2.type = d.type AND (d2.region = d.region OR (d2.region IS NULL AND d.region IS NULL)))')
            ->getQuery()
            ->getResult();

        return collect($documents)->keyBy(
            static fn (LegalDocument $document): string => self::pairKey($document->getType(), $document->getRegion()),
        );
    }

    /**
     * Ключ пары «вид + регион» для коллекции из latestPerPair(); у общего документа регион в ключе пустой.
     */
    public static function pairKey(ConsentType $type, ?string $region): string
    {
        return $type->value . '|' . ($region ?? '');
    }

    /**
     * Последняя версия документа вида для региона; null в регионе — общий документ.
     */
    public function findLatest(ConsentType $type, ?string $region): ?LegalDocument
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(LegalDocument::class, 'd')
            ->where('d.type = :type')
            ->setParameter('type', $type->value)
            ->orderBy('d.version', 'DESC')
            ->setMaxResults(1);

        return $this->inRegion($query, $region)->getQuery()->getOneOrNullResult();
    }

    /**
     * Сохраняет новую версию.
     */
    public function save(LegalDocument $document): void
    {
        $this->entityManager->persist($document);
        $this->entityManager->flush();
    }

    /**
     * Ограничивает запрос регионом; null — документы без региона.
     */
    private function inRegion(QueryBuilder $query, ?string $region): QueryBuilder
    {
        if ($region === null) {
            return $query->andWhere('d.region IS NULL');
        }

        return $query->andWhere('d.region = :region')->setParameter('region', $region);
    }
}
