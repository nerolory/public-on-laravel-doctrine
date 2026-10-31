<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\Common\PageData;
use App\Entities\Language;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Языки интерфейса сайта.
 */
final class LanguageRepository
{
    /** Порядок языков: язык по умолчанию первым, затем по коду. */
    private const array ORDER = ['isDefault' => 'DESC', 'code' => 'ASC'];

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Язык по коду.
     */
    public function find(string $code): ?Language
    {
        return $this->entityManager->find(Language::class, $code);
    }

    /**
     * Все языки: язык по умолчанию первым, затем по коду.
     *
     * @return Collection<int, Language>
     */
    public function all(): Collection
    {
        return collect($this->entityManager->getRepository(Language::class)->findBy([], self::ORDER));
    }

    /**
     * Страница языков для админки.
     *
     * @return PageData<Language>
     */
    public function page(int $page, int $limit): PageData
    {
        $repository = $this->entityManager->getRepository(Language::class);
        $rows = $repository->findBy([], self::ORDER, $limit + 1, ($page - 1) * $limit);

        return PageData::fromOverfetched(collect($rows), $limit, total: $repository->count([]));
    }

    /**
     * Текущий язык по умолчанию.
     */
    public function findDefault(): ?Language
    {
        return $this->entityManager->getRepository(Language::class)->findOneBy(['isDefault' => true]);
    }

    /**
     * Сохраняет язык.
     */
    public function save(Language $language): void
    {
        $this->entityManager->persist($language);
        $this->entityManager->flush();
    }

    /**
     * Делает язык языком по умолчанию и снимает флаг с прежнего в одной транзакции.
     */
    public function switchDefault(Language $language): void
    {
        $this->entityManager->wrapInTransaction(function () use ($language): void {
            foreach ($this->entityManager->getRepository(Language::class)->findBy(['isDefault' => true]) as $current) {
                $current->unsetDefault();
            }

            $language->makeDefault();
            $this->entityManager->persist($language);
        });
    }

    /**
     * Удаляет язык.
     */
    public function delete(Language $language): void
    {
        $this->entityManager->remove($language);
        $this->entityManager->flush();
    }
}
