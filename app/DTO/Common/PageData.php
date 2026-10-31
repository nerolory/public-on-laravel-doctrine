<?php

declare(strict_types=1);

namespace App\DTO\Common;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Страница списка: элементы и признак следующей страницы.
 * Общее число записей, если оно посчитано.
 *
 * @template TItem
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PageData implements Arrayable
{
    /** Номер первой страницы: открывается без параметра page и является наименьшим допустимым. */
    public const int FIRST_PAGE = 1;

    /**
     * @param Collection<int, TItem> $items   элементы страницы
     * @param bool                   $hasMore есть ли следующая страница
     * @param int|null               $total   число записей во всём списке
     */
    public function __construct(
        public Collection $items,
        public bool $hasMore = false,
        public ?int $total = null,
    ) {
    }

    /**
     * Страница из выборки на одну запись больше лимита: лишняя запись показывает, что есть следующая страница.
     *
     * @template TRow
     *
     * @param Collection<int, TRow> $rows  выборка размером до limit + 1
     * @param int|null              $total число записей во всём списке, если посчитано
     *
     * @return self<TRow>
     */
    public static function fromOverfetched(Collection $rows, int $limit, ?int $total = null): self
    {
        return new self($rows->take($limit)->values(), hasMore: $rows->count() > $limit, total: $total);
    }

    /**
     * Та же страница с преобразованными элементами.
     *
     * @template TMapped
     *
     * @param callable(TItem): TMapped $callback
     *
     * @return self<TMapped>
     */
    public function map(callable $callback): self
    {
        return new self($this->items->map($callback)->values(), $this->hasMore, $this->total);
    }

    /**
     * Страница для ответа API; total выводится, если он посчитан.
     *
     * @return array{items: list<mixed>, has_more: bool, total?: int}
     */
    public function toArray(): array
    {
        $page = ['items' => $this->items->toArray(), 'has_more' => $this->hasMore];

        return $this->total === null ? $page : $page + ['total' => $this->total];
    }
}
