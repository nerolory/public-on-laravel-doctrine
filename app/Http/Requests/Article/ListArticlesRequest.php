<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\Http\Requests\Common\SectionFilterRequest;
use App\Traits\ReadsPage;

/**
 * Лента статей: номер страницы и необязательный фильтр по разделу.
 */
final class ListArticlesRequest extends SectionFilterRequest
{
    use ReadsPage;

    /**
     * Правила проверки раздела и номера страницы.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + $this->pageRules();
    }
}
