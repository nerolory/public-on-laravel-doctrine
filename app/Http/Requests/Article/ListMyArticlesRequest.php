<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\Enums\Article\ArticleStatusFilter;
use App\Http\Requests\Common\PageRequest;
use Illuminate\Validation\Rule;

/**
 * «Мои статьи»: номер страницы и необязательный фильтр по состоянию.
 */
final class ListMyArticlesRequest extends PageRequest
{
    /**
     * Правила проверки страницы и состояния; архивные статьи в списке не показываются.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['status' => ['nullable', Rule::enum(ArticleStatusFilter::class)]];
    }

    /**
     * Выбранное состояние или null без фильтра.
     */
    public function status(): ?ArticleStatusFilter
    {
        $status = $this->validated('status');

        return $status === null ? null : ArticleStatusFilter::from((string) $status);
    }
}
