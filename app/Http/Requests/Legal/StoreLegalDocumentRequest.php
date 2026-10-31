<?php

declare(strict_types=1);

namespace App\Http\Requests\Legal;

use App\Entities\LegalDocument;
use App\Entities\User;
use App\Enums\Legal\ConsentType;
use App\Services\Legal\LegalDocumentService;
use App\Services\User\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Новая версия правового документа из админки: публикуют владелец и администратор.
 */
final class StoreLegalDocumentRequest extends FormRequest
{
    /**
     * Документы меняют те, кто меняет настройки сайта.
     */
    public function authorize(PermissionService $permissions): bool
    {
        $user = $this->user();

        return $user instanceof User && $permissions->canManageSettings($user);
    }

    /**
     * Правила проверки вида, региона, заголовка и текста.
     *
     * @return array<string, mixed>
     */
    public function rules(LegalDocumentService $documents): array
    {
        return [
            'type' => ['required', Rule::enum(ConsentType::class)],
            'region' => ['nullable', Rule::in($documents->regions()->all())],
            'title' => ['required', 'string', 'max:' . LegalDocument::TITLE_LENGTH],
            'text' => ['required', 'string', 'max:' . LegalDocument::TEXT_LENGTH],
        ];
    }

    /**
     * Подписи полей в сообщениях об ошибках.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => __('legal.admin.fields.title'), 'text' => __('legal.admin.fields.text')];
    }

    /**
     * Вид согласия.
     */
    public function type(): ConsentType
    {
        return ConsentType::from($this->validated('type'));
    }

    /**
     * Регион; пустое значение — документ для всех регионов.
     */
    public function region(): ?string
    {
        return $this->validated('region');
    }

    /**
     * Заголовок документа.
     */
    public function title(): string
    {
        return (string) $this->validated('title');
    }

    /**
     * Текст документа.
     */
    public function text(): string
    {
        return (string) $this->validated('text');
    }
}
