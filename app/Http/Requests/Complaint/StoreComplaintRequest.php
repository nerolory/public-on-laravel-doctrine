<?php

declare(strict_types=1);

namespace App\Http\Requests\Complaint;

use App\DTO\Complaint\ComplaintData;
use App\Entities\Complaint;
use App\Enums\Complaint\ComplaintReason;
use App\Services\Complaint\ComplaintService;
use App\Services\Complaint\ComplaintTargets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Жалоба читателя. Для причины, требующей пояснения, текст обязателен.
 */
final class StoreComplaintRequest extends FormRequest
{
    /** Длина идентификатора цели: самый длинный из них — UUID альбома. */
    private const int ID_LENGTH = 36;

    /**
     * Правила проверки жалобы.
     *
     * @return array<string, mixed>
     */
    public function rules(ComplaintTargets $targets): array
    {
        return [
            'target' => ['required', Rule::in($targets->keys()->all())],
            'id' => ['required', 'string', 'max:' . self::ID_LENGTH],
            'reason' => ['required', Rule::enum(ComplaintReason::class)],
            'text' => [
                'nullable',
                'string',
                'max:' . Complaint::TEXT_LENGTH,
                Rule::requiredIf($this->requiresText()),
            ],
        ];
    }

    /**
     * Жалоба не на своё и не повторная; иначе ошибка поля id.
     *
     * @return list<callable(Validator): void>
     */
    public function after(ComplaintService $complaints): array
    {
        return [function (Validator $validator) use ($complaints): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $problem = $complaints->fileProblem($this->user(), $this->toData());
            if ($problem !== null) {
                $validator->errors()->add('id', __('messages.complaints.' . $problem->value));
            }
        }];
    }

    /**
     * Жалоба для сервиса; пустое пояснение не сохраняется.
     */
    public function toData(): ComplaintData
    {
        $text = $this->validated('text');
        $trimmed = $text === null ? null : trim($text);

        return new ComplaintData(
            target: (string) $this->validated('target'),
            id: (string) $this->validated('id'),
            reason: ComplaintReason::from((string) $this->validated('reason')),
            text: $trimmed === '' ? null : $trimmed,
        );
    }

    /**
     * Выбранная причина требует пояснения.
     */
    private function requiresText(): bool
    {
        return ComplaintReason::tryFrom((string) $this->input('reason'))?->requiresText() === true;
    }
}
