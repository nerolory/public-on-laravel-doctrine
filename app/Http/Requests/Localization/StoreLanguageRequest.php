<?php

declare(strict_types=1);

namespace App\Http\Requests\Localization;

use App\DTO\Localization\LanguageData;
use App\Entities\Language;
use App\Services\Localization\LanguageService;
use App\Support\Localization\LanguageFiles;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Создание языка и, если в маршруте есть код, его изменение.
 */
final class StoreLanguageRequest extends FormRequest
{
    /**
     * @param LanguageFiles   $languageFiles   папки переводов в lang/
     * @param LanguageService $languageService существующие языки
     */
    public function __construct(
        private readonly LanguageFiles $languageFiles,
        private readonly LanguageService $languageService,
    ) {
    }

    /**
     * Код задаётся только при создании; папка переводов — одна из существующих в lang/.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => $this->editedCode() !== null
                ? ['prohibited']
                : ['bail', 'required', 'string', 'regex:' . Language::CODE_PATTERN, $this->uniqueCode()],
            'name' => ['required', 'string', 'max:' . Language::NAME_LENGTH],
            'file' => ['required', 'string', Rule::in($this->languageFiles->available()->all())],
            'enabled' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * Названия полей в сообщениях об ошибках.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('languages.fields.code'),
            'name' => __('languages.fields.name'),
            'file' => __('languages.fields.file'),
        ];
    }

    /**
     * Данные языка из формы.
     */
    public function languageData(): LanguageData
    {
        $editedCode = $this->editedCode();

        return new LanguageData(
            code: $editedCode === null ? (string) $this->validated('code') : $editedCode,
            name: $this->validated('name'),
            file: $this->validated('file'),
            enabled: $this->boolean('enabled'),
            isDefault: $this->boolean('is_default'),
        );
    }

    /**
     * Код изменяемого языка из адреса или null при создании.
     */
    public function editedCode(): ?string
    {
        $language = $this->route('language');

        return $language instanceof Language ? $language->getCode() : null;
    }

    /**
     * Правило: язык с таким кодом ещё не создан.
     */
    private function uniqueCode(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->languageService->exists((string) $value)) {
                $fail(__('languages.code_taken'));
            }
        };
    }
}
