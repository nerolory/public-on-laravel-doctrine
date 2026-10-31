<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\Entities\Tag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Подсказки для поля тегов по началу введённого текста.
 */
final class SuggestTagsRequest extends FormRequest
{
    /**
     * Правила проверки введённого текста.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'max:' . Tag::MAX_LENGTH]];
    }

    /**
     * Начало имени тега.
     */
    public function prefix(): string
    {
        return $this->validated('q');
    }
}
