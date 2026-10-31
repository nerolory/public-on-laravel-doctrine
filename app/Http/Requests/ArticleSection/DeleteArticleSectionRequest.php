<?php

declare(strict_types=1);

namespace App\Http\Requests\ArticleSection;

use App\Entities\ArticleSection;
use App\Services\Article\ArticleSectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Удаление раздела: корневой раздел удаляется, только если в нём нет подразделов и статей.
 */
final class DeleteArticleSectionRequest extends FormRequest
{
    /**
     * Полей у запроса нет.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Ошибка поля section, если раздел удалить нельзя.
     *
     * @return list<callable(Validator): void>
     */
    public function after(ArticleSectionService $sections): array
    {
        return [function (Validator $validator) use ($sections): void {
            $section = $this->route('section');
            if ($section instanceof ArticleSection && ! $sections->canDelete($section)) {
                $validator->errors()->add('section', __('admin.sections.root_not_empty_error'));
            }
        }];
    }
}
