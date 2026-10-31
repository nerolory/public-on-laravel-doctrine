<?php

declare(strict_types=1);

namespace App\Http\Requests\Complaint;

use App\Enums\Complaint\ComplaintAction;
use App\Enums\Complaint\ComplaintStatus;
use App\Services\Complaint\ComplaintService;
use App\Services\Complaint\ComplaintTargets;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Решение модератора по объекту из очереди жалоб.
 */
final class ResolveComplaintRequest extends FormRequest
{
    /**
     * Правила проверки решения.
     *
     * @return array<string, mixed>
     */
    public function rules(ComplaintTargets $targets): array
    {
        return [
            'target' => ['required', Rule::in($targets->keys()->all())],
            'id' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::enum(ComplaintAction::class)],
        ];
    }

    /**
     * Право на решение по конкретному объекту проверяется после полей: скрыть пользователя модератор может,
     * только если вправе им управлять. Доступ в админку проверяет middleware группы маршрутов.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(ComplaintService $complaints): array
    {
        return [
            function (Validator $validator) use ($complaints): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $complaints->canResolve($this->target(), $this->targetId(), $this->decision(), $this->user())) {
                    throw new AuthorizationException();
                }
            },
        ];
    }

    /**
     * Ключ цели жалоб.
     */
    public function target(): string
    {
        return $this->validated('target');
    }

    /**
     * Идентификатор объекта.
     */
    public function targetId(): int
    {
        return (int) $this->validated('id');
    }

    /**
     * Итоговый статус жалоб по решению модератора.
     */
    public function decision(): ComplaintStatus
    {
        return ComplaintAction::from((string) $this->validated('action'))->decision();
    }
}
