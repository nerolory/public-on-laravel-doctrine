<?php

declare(strict_types=1);

namespace App\Traits;

use App\DTO\Common\PageData;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Чтение параметров текущего запроса внутри страницы MoonShine.
 *
 * MoonShine переиспользует экземпляры страниц между запросами (они пересоздаются сразу после сброса состояния),
 * поэтому запрос берётся заново при каждом обращении.
 */
trait ReadsCurrentRequest
{
    /**
     * Параметр запроса.
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $this->getCore()->getRequest()->get($key, $default);
    }

    /**
     * Номер страницы списка; не меньше первой.
     */
    protected function pageNumber(): int
    {
        return max(PageData::FIRST_PAGE, (int) $this->query('page', PageData::FIRST_PAGE));
    }

    /**
     * Положительный целый id из адреса или null, если параметра нет.
     *
     * @throws EntityNotFoundException id не является положительным целым числом: ответ 404
     */
    protected function queryId(): ?int
    {
        $id = $this->query('id');

        if ($id === null) {
            return null;
        }

        if (! is_string($id) || ! ctype_digit($id) || (int) $id === 0) {
            throw new EntityNotFoundException('The record id in the query string is not a positive integer.');
        }

        return (int) $id;
    }
}
