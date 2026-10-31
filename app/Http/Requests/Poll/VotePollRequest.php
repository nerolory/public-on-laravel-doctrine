<?php

declare(strict_types=1);

namespace App\Http\Requests\Poll;

use App\Services\Poll\PollService;
use App\Support\Settings\ContentLimits;
use App\Traits\ResolvesPoll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Голос в опросе: идентификаторы выбранных вариантов. Доступ к опросу проверяет политика на маршруте.
 */
final class VotePollRequest extends FormRequest
{
    use ResolvesPoll;

    /**
     * Вариантов не больше, чем их может быть в опросе.
     *
     * @return array<string, mixed>
     */
    public function rules(ContentLimits $limits): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:' . $limits->pollOptions()],
            'options.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Опрос открыт, выбор допустим, а повторный голос разрешён настройками опроса.
     *
     * @return list<callable(Validator): void>
     */
    public function after(PollService $polls): array
    {
        return [function (Validator $validator) use ($polls): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $problem = $polls->voteProblem($this->poll(), $this->user(), $this->optionIds());
            if ($problem !== null) {
                $validator->errors()->add('options', __('messages.polls.' . $problem->value));
            }
        }];
    }

    /**
     * Выбранные варианты.
     *
     * @return Collection<int, int>
     */
    public function optionIds(): Collection
    {
        return collect($this->input('options'))->map(static fn (mixed $id): int => (int) $id)->values();
    }
}
