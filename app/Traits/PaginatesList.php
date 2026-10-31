<?php

declare(strict_types=1);

namespace App\Traits;

use App\DTO\Common\PageData;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;
use MoonShine\Crud\Components\Paginator;
use MoonShine\UI\Components\FlexibleRender;

/**
 * Таблица списка админки с постраничной навигацией; ссылки ведут на адрес текущей страницы.
 */
trait PaginatesList
{
    /**
     * Таблица и пагинатор для страницы списка.
     *
     * @param PageData<mixed> $page        элементы страницы и общее число записей
     * @param int             $currentPage номер страницы
     * @param int             $perPage     записей на странице
     *
     * @return list<object>
     *
     * @throws LogicException общее число записей не посчитано
     */
    protected function listComponents(Renderable $table, PageData $page, int $currentPage, int $perPage): array
    {
        $total = $page->total ?? throw new LogicException('Admin list total is not counted.');

        return [
            FlexibleRender::make($table),
            Paginator::make(new LengthAwarePaginator(
                items: $page->items,
                total: $total,
                perPage: $perPage,
                currentPage: $currentPage,
                options: ['path' => $this->getUrl()],
            )),
        ];
    }
}
