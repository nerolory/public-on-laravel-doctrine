<?php

declare(strict_types=1);

namespace App\Http\Requests\Localization;

use App\Entities\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Удаление языка: язык по умолчанию удалить нельзя, сначала нужно назначить другой.
 */
final class DeleteLanguageRequest extends FormRequest
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
     * Ошибка поля language для языка по умолчанию.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $language = $this->route('language');
            if ($language instanceof Language && $language->isDefault()) {
                $validator->errors()->add('language', __('languages.cannot_delete_default'));
            }
        }];
    }
}
