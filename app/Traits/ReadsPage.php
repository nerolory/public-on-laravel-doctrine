<?php

declare(strict_types=1);

namespace App\Traits;

use App\DTO\Common\PageData;

/**
 * Номер страницы списка из параметра ?page= проверенного запроса.
 */
trait ReadsPage
{
    /**
     * Номер запрошенной страницы; по умолчанию первая.
     */
    public function page(): int
    {
        return (int) ($this->validated('page') ?? PageData::FIRST_PAGE);
    }

    /**
     * Правила параметра page: необязательное целое, не меньше первой страницы.
     *
     * @return array<string, list<string>>
     */
    protected function pageRules(): array
    {
        return ['page' => ['nullable', 'integer', 'min:' . PageData::FIRST_PAGE]];
    }
}
