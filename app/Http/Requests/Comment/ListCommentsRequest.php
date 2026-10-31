<?php

declare(strict_types=1);

namespace App\Http\Requests\Comment;

use App\Http\Requests\Common\PageRequest;
use Illuminate\Validation\Rule;

/**
 * Страница веток комментариев с сортировкой.
 */
final class ListCommentsRequest extends PageRequest
{
    /** Сортировка «сначала популярные»; она же по умолчанию. */
    private const string SORT_POPULAR = 'top';

    /** Сортировка «сначала новые». */
    private const string SORT_NEWEST = 'new';

    /**
     * Правила проверки страницы и сортировки.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['sort' => ['nullable', Rule::in([self::SORT_POPULAR, self::SORT_NEWEST])]];
    }

    /**
     * Выбрана ли сортировка «сначала популярные».
     */
    public function popularFirst(): bool
    {
        return $this->sort() === self::SORT_POPULAR;
    }

    /**
     * Сортировка из запроса; без параметра или с пустым значением — по популярности.
     */
    private function sort(): string
    {
        return $this->validated('sort') === self::SORT_NEWEST ? self::SORT_NEWEST : self::SORT_POPULAR;
    }
}
