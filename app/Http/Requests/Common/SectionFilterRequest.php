<?php

declare(strict_types=1);

namespace App\Http\Requests\Common;

use App\Entities\ArticleSection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Необязательный фильтр по разделу из параметра ?section= (адрес раздела).
 */
class SectionFilterRequest extends FormRequest
{
    /** Выражение адреса раздела без разделителей и якорей: латиница, цифры и одиночные дефисы между словами. */
    public const string SLUG_EXPRESSION = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /** Регулярное выражение адреса раздела для проверки запроса: выражение от начала до конца строки. */
    public const string SLUG_PATTERN = '/^' . self::SLUG_EXPRESSION . '$/';

    /**
     * Правила проверки адреса раздела.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section' => ['nullable', 'string', 'max:' . ArticleSection::SLUG_LENGTH, 'regex:' . self::SLUG_PATTERN],
        ];
    }

    /**
     * Адрес раздела или null без фильтра.
     */
    public function section(): ?string
    {
        return $this->validated('section');
    }
}
