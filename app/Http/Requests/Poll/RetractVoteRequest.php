<?php

declare(strict_types=1);

namespace App\Http\Requests\Poll;

use App\Services\Poll\PollService;
use App\Traits\ResolvesPoll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Отзыв голоса: опрос открыт и разрешает менять голос. Доступ к опросу проверяет политика на маршруте.
 */
final class RetractVoteRequest extends FormRequest
{
    use ResolvesPoll;

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
     * Ошибка поля options, если голос отозвать нельзя.
     *
     * @return list<callable(Validator): void>
     */
    public function after(PollService $polls): array
    {
        return [function (Validator $validator) use ($polls): void {
            $problem = $polls->retractProblem($this->poll());
            if ($problem !== null) {
                $validator->errors()->add('options', __('messages.polls.' . $problem->value));
            }
        }];
    }
}
