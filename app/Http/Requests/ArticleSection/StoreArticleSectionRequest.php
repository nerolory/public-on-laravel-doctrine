<?php

declare(strict_types=1);

namespace App\Http\Requests\ArticleSection;

use App\Entities\ArticleSection;
use App\Http\Requests\Common\SectionFilterRequest;
use App\Services\Article\ArticleSectionService;
use App\Traits\AcceptsSeo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Создание раздела и, если в маршруте есть id, его изменение. Адрес задаётся только при изменении.
 */
final class StoreArticleSectionRequest extends FormRequest
{
    use AcceptsSeo;

    /**
     * Правила проверки названия, адреса, родителя и SEO-полей.
     *
     * @return array<string, mixed>
     */
    public function rules(ArticleSectionService $sections): array
    {
        $id = $this->sectionId();

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:' . ArticleSection::NAME_LENGTH,
                $this->uniqueRule($sections->nameInUse(...), $id, 'messages.sections.name_taken'),
            ],
            'slug' => $id === null
                ? ['prohibited']
                : [
                    'bail',
                    'required',
                    'string',
                    'max:' . ArticleSection::SLUG_LENGTH,
                    'regex:' . SectionFilterRequest::SLUG_PATTERN,
                    $this->uniqueRule($sections->slugInUse(...), $id, 'messages.sections.slug_taken'),
                ],
            ...$this->seoRules(),
            'parent_id' => ['bail', 'nullable', 'integer', 'min:1', $this->parentRule($sections, $id)],
        ];
    }

    /**
     * Сообщение о неверном формате адреса.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => __('messages.sections.slug_format')];
    }

    /**
     * Название раздела.
     */
    public function name(): string
    {
        return $this->validated('name');
    }

    /**
     * Адрес раздела.
     */
    public function slug(): string
    {
        return $this->validated('slug');
    }

    /**
     * Родительский раздел; null — корневой.
     */
    public function parentId(): ?int
    {
        $parent = $this->validated('parent_id');

        return $parent === null ? null : (int) $parent;
    }

    /**
     * Значение поля не занято другим разделом.
     *
     * @param Closure(string, ?int): bool $inUse
     */
    private function uniqueRule(Closure $inUse, ?int $id, string $message): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($inUse, $id, $message): void {
            if ($inUse((string) $value, $id)) {
                $fail(__($message));
            }
        };
    }

    /**
     * Родитель существует, и это не сам раздел и не его подраздел.
     */
    private function parentRule(ArticleSectionService $sections, ?int $id): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($sections, $id): void {
            if (! $sections->exists((int) $value)) {
                $fail(__('messages.sections.parent_missing'));

                return;
            }
            if ($id !== null && ! $sections->canMoveTo($id, (int) $value)) {
                $fail(__('messages.sections.parent_cycle'));
            }
        };
    }

    /**
     * Идентификатор раздела из маршрута; при создании его нет.
     */
    private function sectionId(): ?int
    {
        $section = $this->route('section');

        return $section instanceof ArticleSection ? $section->persistedId() : null;
    }
}
