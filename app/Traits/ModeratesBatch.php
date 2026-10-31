<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationStatus;
use Illuminate\Validation\Rules\Enum;

/**
 * Пакетное действие модератора над списком материалов: набор идентификаторов, действие и причина отклонения.
 */
trait ModeratesBatch
{
    /**
     * Доступ к админке проверяет посредник маршрута.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Действие модератора.
     */
    public function action(): ModerationAction
    {
        return ModerationAction::from($this->validated('action'));
    }

    /**
     * Причина отклонения; null, если она не нужна.
     */
    public function reason(): ?string
    {
        return $this->validated('reason');
    }

    /**
     * Правила пакетного действия.
     *
     * @param list<string|object> $idRules правила каждого идентификатора из списка
     * @param Enum                $actions допустимые действия
     *
     * @return array<string, mixed>
     */
    protected function batchRules(array $idRules, Enum $actions): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:' . ModerationAction::BATCH_LIMIT],
            'ids.*' => $idRules,
            'action' => ['required', $actions],
            'reason' => [
                'required_if:action,' . ModerationAction::Reject->value,
                'nullable',
                'string',
                'max:' . ModerationStatus::REASON_LENGTH,
            ],
        ];
    }
}
