<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Article;
use App\Entities\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;

/**
 * Теги статей: поиск по бизнес-ключу и подсказки для поля тегов.
 */
final class TagRepository
{
    /** Символ экранирования для LIKE: в тегах его не бывает. */
    private const string LIKE_ESCAPE = '!';

    /**
     * @param EntityManagerInterface $entityManager менеджер сущностей Doctrine
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Теги по именам: существующие находятся по ключу, недостающие создаются.
     * Порядок — как у автора, повторы убираются.
     *
     * @param Collection<int, string> $names
     *
     * @return Collection<int, Tag>
     */
    public function resolve(Collection $names): Collection
    {
        $wanted = $names
            ->filter(static fn (string $name): bool => Tag::keyOf($name) !== '')
            ->unique(static fn (string $name): string => Tag::keyOf($name))
            ->values();
        if ($wanted->isEmpty()) {
            return collect();
        }
        $found = $this->entityManager->getRepository(Tag::class)
            ->findBy(['nameKey' => $wanted->map(Tag::keyOf(...))->all()]);
        $existing = collect($found)->keyBy(static fn (Tag $tag): string => $tag->getNameKey());

        return $wanted->map(function (string $name) use ($existing): Tag {
            $tag = $existing->get(Tag::keyOf($name)) ?? Tag::create($name);
            $this->entityManager->persist($tag);

            return $tag;
        });
    }

    /**
     * Подсказки для поля тегов: теги, начинающиеся с введённого текста, сначала самые используемые.
     *
     * @return Collection<int, string>
     */
    public function suggest(string $prefix, int $limit): Collection
    {
        // Символы шаблона LIKE во вводе ищутся буквально.
        $escape = self::LIKE_ESCAPE;
        $key = strtr(Tag::keyOf($prefix), [$escape => $escape . $escape, '%' => $escape . '%', '_' => $escape . '_']);

        // Число статей нужно только для сортировки: в выборку оно не попадает (HIDDEN).
        return collect($this->entityManager->createQueryBuilder()
            ->select('t.name, COUNT(a.id) AS HIDDEN uses')
            ->from(Article::class, 'a')
            ->join('a.tags', 't')
            ->where("t.nameKey LIKE :prefix ESCAPE '{$escape}'")
            ->setParameter('prefix', $key . '%')
            ->groupBy('t.id, t.name')
            ->orderBy('uses', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult());
    }
}
